<?php

namespace App\Actions\Floor;

use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\User;

class RecordFloorActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(string $event, DiningTable $table, User $by, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $table->getMorphClass(),
            'subject_id' => $table->id,
            'user_id' => $by->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
