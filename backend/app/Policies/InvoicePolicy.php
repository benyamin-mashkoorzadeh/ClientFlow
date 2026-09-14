<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->workspace_id === $user->activeWorkspace()?->id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->workspace_id === $user->activeWorkspace()?->id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->workspace_id === $user->activeWorkspace()?->id;
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $invoice->workspace_id === $user->activeWorkspace()?->id;
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
