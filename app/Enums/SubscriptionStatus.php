<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';

    public function allowsAccess(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }
}
