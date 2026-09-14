<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function view(User $user, Task $task): bool
    {
        return $task->workspace_id === $user->activeWorkspace()?->id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function update(User $user, Task $task): bool
    {
        return $task->workspace_id === $user->activeWorkspace()?->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->workspace_id === $user->activeWorkspace()?->id;
    }

    public function restore(User $user, Task $task): bool
    {
        return $task->workspace_id === $user->activeWorkspace()?->id;
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }
}
