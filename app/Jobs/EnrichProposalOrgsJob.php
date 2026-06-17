<?php

namespace App\Jobs;

use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * After a proposal is created from a dumped document, research the client
 * company and the issuing agency on the web (search-grounded) and save a concise
 * factual background to each record when it has none. That enriched background
 * is then auto-embedded (EmbeddingObserver) so the Proposal Writer and QuakeBot
 * can draft and answer with real, current context. Runs in the background so
 * intake stays fast; no-ops when the provider can't research the web.
 */
class EnrichProposalOrgsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $uniqueFor = 600;

    public function __construct(public int $proposalId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->proposalId;
    }

    public function handle(AiProviderInterface $ai): void
    {
        $proposal = ProposalSubmission::with(['company', 'agency'])->find($this->proposalId);
        if (! $proposal) {
            return;
        }

        $targets = [
            ['org' => $proposal->company, 'kind' => 'company'],
            ['org' => $proposal->agency, 'kind' => 'agency'],
        ];

        foreach ($targets as $t) {
            $org = $t['org'];
            if (! $org || filled($org->notes)) {
                continue; // skip when missing or already has notes
            }

            $query = $t['kind'] === 'agency'
                ? "Give a concise 3-4 sentence factual background on the U.S. government agency or office \"{$org->name}\": "
                    . 'its mission, what it does, and the kinds of products/services it procures. '
                    . 'If you are not confident it is a real agency, say so plainly.'
                : "Give a concise 3-4 sentence factual background on the organization \"{$org->name}\" for a government "
                    . 'proposal: what they do, their industry/sector, and headquarters location if known. '
                    . 'If you are not confident it is a real organization, say so plainly.';

            try {
                $background = trim($ai->research($query));
            } catch (\Throwable $e) {
                Log::warning('Org web-research failed', ['org' => $org->id, 'error' => $e->getMessage()]);
                $background = '';
            }

            if ($background !== '') {
                $org->forceFill(['notes' => "Web research (QuakeAI):\n" . $background])->save();
            }
        }
    }
}
