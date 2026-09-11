<?php

namespace App\Enums;

enum AdmissionType: string
{
    case EMERGENCIA = 'emergencia';
    case URGENCIA = 'urgencia';
    case HOSPITALIZACION = 'hospitalizacion';
    case COEX = 'coex';
    case COEX_EMERGENCIA = 'coex_emergencia';

    public function label(): string
    {
        return match ($this) {
            self::EMERGENCIA => 'Emergencia',
            self::URGENCIA => 'Urgencia',
            self::HOSPITALIZACION => 'Hospitalización',
            self::COEX => 'COEX',
            self::COEX_EMERGENCIA => 'COEX Emergencia',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $carry, self $type): array {
            $carry[$type->value] = $type->label();

            return $carry;
        }, []);
    }
}
