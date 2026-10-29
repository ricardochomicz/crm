<?php

namespace App\Enums;

enum TenantUserRole: string
{
    case OWNER = 'OWNER';
    case MANAGER = 'MANAGER';
    case CONSULTANT = 'CONSULTANT';
    case ASSISTANT = 'ASSISTANT';
}
