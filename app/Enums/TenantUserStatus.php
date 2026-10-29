<?php

namespace App\Enums;

enum TenantUserStatus: string
{
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
}
