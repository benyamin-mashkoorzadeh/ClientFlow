<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $client->workspace_id === $user->activeWorkspace()?->id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function update(User $user, Client $client): bool
    {
        return $client->workspace_id === $user->activeWorkspace()?->id;
    }

    public function delete(User $user, Client $client): bool
    {
        return $client->workspace_id === $user->activeWorkspace()?->id;
    }

    public function restore(User $user, Client $client): bool
    {
        return $client->workspace_id === $user->activeWorkspace()?->id;
    }

    public function forceDelete(User $user, Client $client): bool
    {
        return false;
    }
}
