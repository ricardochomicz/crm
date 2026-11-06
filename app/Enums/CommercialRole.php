<?php

namespace App\Enums;

enum CommercialRole: string
{
    case CONSULTANT = 'CONSULTANT';
    case SUPERVISOR = 'SUPERVISOR';
    case MANAGER = 'MANAGER';
    case REFERRER = 'REFERRER';
    case PARTNER = 'PARTNER';

    public function label(): string
    {
        return match ($this) {
            self::CONSULTANT => 'Consultor',
            self::SUPERVISOR => 'Supervisor',
            self::MANAGER => 'Gerente',
            self::REFERRER => 'Indicador',
            self::PARTNER => 'Parceiro',
        };
    }
}
