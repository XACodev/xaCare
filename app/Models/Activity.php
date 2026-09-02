<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            if (! $activity->subject_type || ! $activity->subject_id) {
                return;
            }

            $subject = $activity->subject()->withoutGlobalScopes()->first();

            if ($subject && isset($subject->hospital_id)) {
                $activity->hospital_id = $subject->hospital_id;
            }
        });
    }
}
