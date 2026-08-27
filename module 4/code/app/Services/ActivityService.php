<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

/**
 * Per-collection chronological feed of meaningful events.
 *
 * record() never throws: an activity row failing to write must not roll back
 * the real action (adding a paper, posting a comment) that it describes.
 */
class ActivityService
{
    /** @param array<string, mixed> $metadata */
    public function record(Collection $collection, User $actor, ActivityType $type, array $metadata = []): void
    {
        try {
            Activity::create([
                'collection_id' => $collection->id,
                'user_id' => $actor->id,
                'type' => $type,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not record activity', [
                'collection_id' => $collection->id,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return EloquentCollection<int, Activity> */
    public function forCollection(Collection $collection, int $limit = 30): EloquentCollection
    {
        return $collection->activities()
            ->with('user:id,name')
            ->limit($limit)
            ->get();
    }
}
