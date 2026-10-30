<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The one way to write to the activity log. Staff actions pass the acting
 * user (or rely on the signed-in one); automations pass none and appear as
 * "Automation".
 */
class ActivityRecorder
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $by = null,
        bool $automated = false,
    ): ActivityLog {
        $user = $automated ? null : ($by ?? Auth::user());

        return ActivityLog::create([
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => $user instanceof User ? $user->id : null,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}
