<?php

namespace App\Services\BidSources;

use App\Models\Opportunity;
use Illuminate\Support\Str;

class OpportunityDeduplicationService
{
    public function computeHash(BidSourceResultDTO $dto): string
    {
        $normalized = implode('|', [
            $dto->source,
            $dto->externalId,
            Str::slug((string) $dto->solicitationNumber),
        ]);
        return hash('sha256', $normalized);
    }

    public function findDuplicate(int $organizationId, BidSourceResultDTO $dto): ?Opportunity
    {
        $hash = $this->computeHash($dto);

        // Check by canonical hash first
        $byHash = Opportunity::where('organization_id', $organizationId)
            ->where('canonical_hash', $hash)
            ->first();
        if ($byHash) return $byHash;

        // Check by same source + external ID
        if ($dto->externalId) {
            $byExternalId = Opportunity::where('organization_id', $organizationId)
                ->where('source', $dto->source)
                ->where('external_id', $dto->externalId)
                ->first();
            if ($byExternalId) return $byExternalId;
        }

        // Cross-source check by solicitation number
        if ($dto->solicitationNumber) {
            $bySolNum = Opportunity::where('organization_id', $organizationId)
                ->where('solicitation_number', $dto->solicitationNumber)
                ->where('source', '!=', $dto->source)
                ->first();
            if ($bySolNum) {
                $bySolNum->update(['is_duplicate_flagged' => true]);
                return $bySolNum;
            }
        }

        return null;
    }

    public function flagPossibleDuplicate(Opportunity $opportunity): void
    {
        $opportunity->update(['is_duplicate_flagged' => true]);
    }
}
