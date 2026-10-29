<?php

namespace App\Enums;

enum TenantUserRole: string
{
    case Owner = 'OWNER';
    case Manager = 'MANAGER';
    case Consultant = 'CONSULTANT';
    case Assistant = 'ASSISTANT';
}
