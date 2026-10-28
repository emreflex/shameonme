<?php

namespace App\Services;

use App\Models\AuditLog;

final class Audit
{
    public static function record(string $action, object $subject, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject_type' => class_basename($subject), 'subject_id' => $subject->id, 'before' => $before, 'after' => $after, 'reason' => $reason]);
    }
}
