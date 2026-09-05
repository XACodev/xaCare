<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    /**
     * A propósito NO usa BelongsToTenant: esa trait aborta 422 cuando no hay
     * hospital_id resoluble, pero la actividad de plataforma (ej. un super
     * admin editando un Hospital, que no tiene hospital_id propio) es
     * legítimamente global. La columna es nullable justo para este caso.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $activity): void {
            if (! $activity->subject_type || ! $activity->subject_id) {
                return;
            }

            $subject = $activity->subject()->withoutGlobalScopes()->first();

            if (! $subject) {
                return;
            }

            if ($subject instanceof Hospital) {
                $activity->hospital_id = $subject->id;
            } elseif (isset($subject->hospital_id)) {
                $activity->hospital_id = $subject->hospital_id;
            }
        });
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
