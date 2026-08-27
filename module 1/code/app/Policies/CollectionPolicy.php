<?php

namespace App\Policies;

use App\Enums\MemberRole;
use App\Models\Collection;
use App\Models\User;

/**
 * Collection access is role-based, mirroring ScholarDesk's
 * requireCollectionRole(min) middleware:
 *
 *   VIEWER  — read papers, notes, comments, activity
 *   EDITOR  — also add/remove papers, post comments, run AI
 *   OWNER   — also manage members, rename, delete
 *
 * The check always goes through Collection::roleFor(), which treats the
 * creator as an owner even if the membership row is missing.
 */
class CollectionPolicy
{
    public function view(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Viewer);
    }

    public function update(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Owner);
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Owner);
    }

    /** Adding/removing papers is an editor-level mutation. */
    public function managePapers(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Editor);
    }

    /** Only the owner may invite, re-role or remove collaborators. */
    public function manageMembers(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Owner);
    }

    public function comment(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Editor);
    }

    /** Running AI over shared material requires more than read access. */
    public function useAi(User $user, Collection $collection): bool
    {
        return $collection->userCanAtLeast($user, MemberRole::Editor);
    }
}
