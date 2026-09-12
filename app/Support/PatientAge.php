<?php

namespace App\Support;

use App\Enums\PatientCategory;
use Carbon\Carbon;

class PatientAge
{
    public function __construct(
        public readonly int $years,
        public readonly int $months,
        public readonly int $days,
    ) {}

    public static function from(?Carbon $birthDate, ?Carbon $reference = null): self
    {
        if (! $birthDate) {
            return new self(0, 0, 0);
        }

        $reference ??= now();
        $diff = $birthDate->diff($reference);

        return new self(
            years: (int) $diff->y,
            months: (int) $diff->m,
            days: (int) $diff->d,
        );
    }

    public function isMinor(): bool
    {
        return $this->years < 18;
    }

    public function isNewborn(): bool
    {
        return $this->years === 0 && $this->months === 0 && $this->days <= 28;
    }

    public function category(): PatientCategory
    {
        return PatientCategory::fromAge($this);
    }

    public function formatted(): string
    {
        if ($this->years > 0) {
            return $this->years.' '.($this->years === 1 ? 'año' : 'años');
        }

        if ($this->months > 0) {
            $text = $this->months.' '.($this->months === 1 ? 'mes' : 'meses');

            if ($this->days > 0) {
                $text .= ' '.$this->days.' '.($this->days === 1 ? 'día' : 'días');
            }

            return $text;
        }

        if ($this->days > 0) {
            return $this->days.' '.($this->days === 1 ? 'día' : 'días');
        }

        return '0 días';
    }

    public function fullFormatted(): string
    {
        $parts = [];

        if ($this->years > 0) {
            $parts[] = $this->years.' '.($this->years === 1 ? 'año' : 'años');
        }

        if ($this->months > 0) {
            $parts[] = $this->months.' '.($this->months === 1 ? 'mes' : 'meses');
        }

        if ($this->days > 0 || empty($parts)) {
            $days = $this->days;
            $parts[] = $days.' '.($days === 1 ? 'día' : 'días');
        }

        return implode(' ', $parts);
    }
}
