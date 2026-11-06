<?php

namespace App\Enums;

enum OrderType: string
{
    case NEW = 'NEW';
    case PORTABILITY = 'PORTABILITY';
    case RENEWAL = 'RENEWAL';
    case INCREMENT = 'INCREMENT';
    case INCREMENT_PORT_IN = 'INCREMENT_PORT_IN';
    case OWNERSHIP_TRANSFER_PJ_PJ = 'OWNERSHIP_TRANSFER_PJ_PJ';
    case OWNERSHIP_TRANSFER_PF_PJ = 'OWNERSHIP_TRANSFER_PF_PJ';
    case CROSS_PORTABILITY_PF_PJ = 'CROSS_PORTABILITY_PF_PJ';
    case CROSS_PORTABILITY_PJ_PJ = 'CROSS_PORTABILITY_PJ_PJ';
    case M2M = 'M2M';
    case FIBER = 'FIBER';
    case BROADBAND = 'BROADBAND';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Novo',
            self::PORTABILITY => 'Portabilidade',
            self::RENEWAL => 'Renovação',
            self::INCREMENT => 'Incremento',
            self::INCREMENT_PORT_IN => 'Incremento PortIn',
            self::OWNERSHIP_TRANSFER_PJ_PJ => 'Transferência de Titularidade PJ-PJ',
            self::OWNERSHIP_TRANSFER_PF_PJ => 'Transferência de Titularidade PF-PJ',
            self::CROSS_PORTABILITY_PF_PJ => 'Portabilidade Cruzada PF-PJ',
            self::CROSS_PORTABILITY_PJ_PJ => 'Portabilidade Cruzada PJ-PJ',
            self::M2M => 'M2M',
            self::FIBER => 'Fibra',
            self::BROADBAND => 'Banda Larga',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
