<?php

namespace App\Enums;

enum TenantType: string
{
    case Single = 'PF';
    case Company = 'PJ';
}
