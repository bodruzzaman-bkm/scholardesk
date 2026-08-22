<?php

namespace App\Policies;

use App\Models\Highlight;
use App\Models\User;

class HighlightPolicy
{
    public function update(User $user, Highlight $highlight): bool
    {
        return $user->id === $highlight->user_id;
    }

    public function delete(User $user, Highlight $highlight): bool
    {
        return $user->id === $highlight->user_id;
    }
}
