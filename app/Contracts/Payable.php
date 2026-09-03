<?php

namespace App\Contracts;

use Carbon\Carbon;

interface Payable
{
    public function isPaid(): bool;

    public function paidAt(): ?Carbon;
}
