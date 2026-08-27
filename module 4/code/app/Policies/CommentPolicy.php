<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /** Authors edit their own comments. Admins do not edit others' words. */
    public function update(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    /**
     * The author may delete their own comment; an administrator may remove
     * one as moderation.
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isAdmin();
    }

    /** Hiding is a moderation action, reserved for administrators. */
    public function moderate(User $user, Comment $comment): bool
    {
        return $user->isAdmin();
    }
}
