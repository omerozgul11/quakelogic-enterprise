<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\CreditNoteStatus;
use App\Modules\Finance\Models\CreditNote;

class CreditNoteService
{
    public function __construct(private readonly FinanceNumberService $numbers) {}

    public function issue(int $organizationId, int $actorId, array $data): CreditNote
    {
        return CreditNote::create([
            'organization_id' => $organizationId,
            'created_by' => $actorId,
            'company_id' => $data['company_id'] ?? null,
            'crm_invoice_id' => $data['crm_invoice_id'] ?? null,
            'number' => $data['number'] ?? $this->numbers->generateCreditNote($organizationId),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'reason' => $data['reason'] ?? null,
            'status' => $data['status'] ?? CreditNoteStatus::Open->value,
            'issued_at' => $data['issued_at'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function apply(CreditNote $note): CreditNote
    {
        $note->update(['status' => CreditNoteStatus::Applied]);

        return $note;
    }

    public function void(CreditNote $note): CreditNote
    {
        $note->update(['status' => CreditNoteStatus::Void]);

        return $note;
    }
}
