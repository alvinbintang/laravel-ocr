<?php

namespace App\Services\Shared;

use Illuminate\Database\Eloquent\Model;

class LogActivityService
{
    /**
     * Log an activity
     *
     * @param string $action
     * @param Model|null $model
     * @param string $description
     * @param array $before
     * @param array $after
     * @return void
     */
    public function log(
        string $action,
        ?Model $model = null,
        string $description = '',
        array $before = [],
        array $after = []
    ): void {
        // Log activity implementation
        // This can be extended to save to database, file, or external service
        \Log::info('Activity Log', [
            'action' => $action,
            'model' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description,
            'before' => $before,
            'after' => $after,
            'user_id' => auth()->id(),
            'timestamp' => now()
        ]);
    }
}