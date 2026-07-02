<?php

namespace App\Observers;

use App\Jobs\ReindexEmbeddingJob;
use App\Models\Agency;
use App\Models\ComplianceItem;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\FollowUp;
use App\Models\Library\LibraryDocument;
use App\Models\ProposalFile;
use App\Models\ProposalNote;
use App\Models\ProposalSection;
use App\Models\ProposalSubmission;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the knowledge base in sync with the org's own data: on any create /
 * update / delete of an indexed record, queue a re-embed so QuakeBot's answers
 * reflect the latest content. Registered against each model below in
 * AppServiceProvider.
 *
 * Opportunities are deliberately NOT observed: they arrive in bulk from the
 * SAM.gov sync (≈2,000 at a time), so per-row reindexing would flood the queue
 * and burn the Gemini free-tier quota. They're refreshed by the nightly
 * `kb:embed --fresh` schedule instead.
 */
class EmbeddingObserver
{
    /** @var array<class-string,string> model class => KB source kind */
    public const KIND_MAP = [
        ProposalSubmission::class => 'proposal',
        ProposalNote::class => 'proposal_note',
        ProposalSection::class => 'proposal_section',
        ProposalFile::class => 'proposal_file',
        Company::class => 'company',
        Contact::class => 'contact',
        Agency::class => 'agency',
        FollowUp::class => 'follow_up',
        Contract::class => 'contract',
        ComplianceItem::class => 'compliance_item',
        LibraryDocument::class => 'library_document',
    ];

    public function saved(Model $model): void
    {
        $this->queue($model);
    }

    public function deleted(Model $model): void
    {
        $this->queue($model);
    }

    public function restored(Model $model): void
    {
        $this->queue($model);
    }

    private function queue(Model $model): void
    {
        $kind = self::KIND_MAP[$model::class] ?? null;
        $orgId = $this->orgIdFor($model);
        if ($kind === null || $orgId === null) {
            return;
        }

        ReindexEmbeddingJob::dispatch($orgId, $kind, $model::class, (int) $model->getKey());
    }

    /** Resolve the owning organization, deriving it via the parent proposal for files/notes. */
    private function orgIdFor(Model $model): ?int
    {
        if (! empty($model->organization_id)) {
            return (int) $model->organization_id;
        }

        $proposalId = $model->proposal_submission_id ?? null;
        if ($proposalId) {
            $orgId = ProposalSubmission::withTrashed()->where('id', $proposalId)->value('organization_id');

            return $orgId ? (int) $orgId : null;
        }

        return null;
    }
}
