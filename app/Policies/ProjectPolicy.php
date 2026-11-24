<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Global admin can perform all project actions.
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user can access the project listing.
     * Actual project visibility is filtered in the controller.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Owner or project member can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return (int) $project->owner_id === (int) $user->id
            || $project->members()
                ->where('users.id', $user->id)
                ->exists();
    }

    /**
     * Authenticated users can create projects.
     */
    public function create(User $user): bool
    {
        return true;
    }

	/**
 * Project owner and members can view the member list.
 */
public function viewMembers(User $user, Project $project): bool
{
    return $this->view($user, $project);
}

/**
 * Only project owner can add members.
 */
public function addMember(User $user, Project $project): bool
{
    return (int) $project->owner_id === (int) $user->id;
}

/**
 * Only project owner can change member roles.
 */
public function updateMemberRole(User $user, Project $project): bool
{
    return (int) $project->owner_id === (int) $user->id;
}

/**
 * Only project owner can remove members.
 */
public function removeMember(User $user, Project $project): bool
{
    return (int) $project->owner_id === (int) $user->id;
}

    /**
     * Owner or project manager can update.
     */
    public function update(User $user, Project $project): bool
    {
        if ((int) $project->owner_id === (int) $user->id) {
            return true;
        }

        return $project->members()
            ->where('users.id', $user->id)
            ->wherePivot('role', 'manager')
            ->exists();
    }

    /**
     * Only owner or global admin can delete.
     */
    public function delete(User $user, Project $project): bool
    {
        return (int) $project->owner_id === (int) $user->id;
    }

    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}