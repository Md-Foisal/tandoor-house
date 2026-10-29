<?php

namespace App\Actions\Team;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TeamActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, Model $subject, User $by, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'user_id' => $by->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
