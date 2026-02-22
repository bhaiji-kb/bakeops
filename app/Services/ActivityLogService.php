<?php

namespace App\Services;

use App\Models\ActivityLog;
use Throwable;

class ActivityLogService
{
    public function log(
        string $module,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => empty($oldValues) ? null : $oldValues,
                'new_values' => empty($newValues) ? null : $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
