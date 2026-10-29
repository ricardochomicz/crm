<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'PENDING';
    case Trialing = 'TRIALING';
    case Active = 'ACTIVE';
    case PastDue = 'PAST_DUE';
    case Cancelled = 'CANCELLED';
}
