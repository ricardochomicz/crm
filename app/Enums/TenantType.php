<?php

namespace App\Enums;

enum TenantType: string
{
    case Individual = 'PF';
    case Company = 'PJ';
}
