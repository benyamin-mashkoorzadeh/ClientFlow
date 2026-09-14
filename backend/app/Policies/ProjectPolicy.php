<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function view(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->activeWorkspace()?->id;
    }

    public function create(User $user): bool
    {
        return ! is_null($user->activeWorkspace());
    }

    public function update(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->activeWorkspace()?->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->activeWorkspace()?->id;
    }

    public function restore(User $user, Project $project): bool
    {
        return $project->workspace_id === $user->activeWorkspace()?->id;
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
