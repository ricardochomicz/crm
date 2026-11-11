<?php

namespace App\Enums;

enum OrderStatusCode: string
{
    case Triage = 'TRIAGE';
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Correction = 'CORRECTION';
    case PartiallyActivated = 'PARTIALLY_ACTIVATED';

    public function requiresActivation(): bool
    {
        return in_array($this, [self::Active, self::Completed, self::PartiallyActivated], true);
    }
}
