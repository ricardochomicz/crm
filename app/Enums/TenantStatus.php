<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Cancelled = 'CANCELLED';
}
