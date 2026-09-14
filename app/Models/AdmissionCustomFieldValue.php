<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionCustomFieldValue extends Model
{
    protected $fillable = ['admission_id', 'custom_field_id', 'value'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(AdmissionTypeCustomField::class, 'custom_field_id');
    }
}
