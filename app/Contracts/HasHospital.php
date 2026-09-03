<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface HasHospital
{
    public function hospital(): BelongsTo;
}
