<?php

namespace App\Policies\Crm;

use App\Models\Crm\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Invoice $invoice): bool { return $user->organization_id === $invoice->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage invoices'); }
    public function update(User $user, Invoice $invoice): bool { return $user->organization_id === $invoice->organization_id && $user->can('manage invoices'); }
    public function delete(User $user, Invoice $invoice): bool { return $user->organization_id === $invoice->organization_id && $user->can('manage invoices'); }
}
