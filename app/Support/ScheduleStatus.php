<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ScheduleStatus
{
    public static function for(Carbon $scheduledFor, bool $isComplete, Carbon $now): string
    {
        if ($isComplete) {
            return 'done';
        }

        return $now->greaterThan($scheduledFor->copy()->addMinutes(30))
            ? 'overdue'
            : 'due';
    }
}
