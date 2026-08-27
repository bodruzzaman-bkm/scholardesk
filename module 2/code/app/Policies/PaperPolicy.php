<?php

namespace App\Policies;

use App\Models\Paper;
use App\Models\User;

/**
 * Papers are owned by one user. Collaborators on a collection containing the
 * paper get read access (view + read the PDF), but never write access —
 * editing metadata, deleting, or annotating stays with the owner.
 */
class PaperPolicy
{
    public function view(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper) || $this->sharedWith($user, $paper) || $user->isAdmin();
    }

    /** Reading the PDF follows view access, so collaborators can read it. */
    public function read(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper) || $this->sharedWith($user, $paper);
    }

    public function update(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper);
    }

    public function delete(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper);
    }

    /**
     * Highlights and notes are private to their author, so only the paper's
     * owner may create them. A collaborator reading a shared PDF does not get
     * to write on someone else's copy.
     */
    public function annotate(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper);
    }

    /** AI over a paper follows read access. */
    public function useAi(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper) || $this->sharedWith($user, $paper);
    }

    /**
     * Commenting follows read access, not annotate access.
     *
     * Highlights and notes are one person's private working material, so they
     * stay with the owner. A comment is the opposite — it is addressed to the
     * other people who can see the paper, which is exactly the collaborators
     * on a collection containing it (requirement 18).
     */
    public function comment(User $user, Paper $paper): bool
    {
        return $this->owns($user, $paper) || $this->sharedWith($user, $paper);
    }

    private function owns(User $user, Paper $paper): bool
    {
        return $user->id === $paper->user_id;
    }

    /** True when the paper sits in a collection this user is a member of. */
    private function sharedWith(User $user, Paper $paper): bool
    {
        return $paper->collections()
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
