<?php

namespace App\Enums;

enum TenantInvitationStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Revoked = 'REVOKED';
}
