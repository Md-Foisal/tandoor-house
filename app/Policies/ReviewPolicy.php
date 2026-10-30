<?php

namespace App\Policies;

use App\Enums\StaffArea;
use App\Models\Review;
use App\Models\User;

/** Staff moderation. Customers write reviews through OrderPolicy::review. */
class ReviewPolicy
{
    /** Publishing and hiding, one at a time or in bulk. */
    public function moderate(User $user): bool
    {
        return StaffArea::Reviews->allows($user->role);
    }

    public function reply(User $user, Review $review): bool
    {
        return StaffArea::Reviews->allows($user->role);
    }

    public function removeReply(User $user, Review $review): bool
    {
        return StaffArea::Reviews->allows($user->role);
    }
}
