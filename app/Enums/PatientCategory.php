<?php

namespace App\Enums;

enum PatientCategory: string
{
    case RECIEN_NACIDO = 'recien_nacido';
    case NEONATO = 'neonato';
    case NINO = 'nino';
    case ADOLESCENTE = 'adolescente';
    case ADULTO = 'adulto';
    case ADULTO_MAYOR = 'adulto_mayor';

    public function label(): string
    {
        return match ($this) {
            self::RECIEN_NACIDO => 'Recién nacido/a',
            self::NEONATO => 'Neonato/a',
            self::NINO => 'Niño/a',
            self::ADOLESCENTE => 'Adolescente',
            self::ADULTO => 'Adulto/a',
            self::ADULTO_MAYOR => 'Adulto/a de tercera edad',
        };
    }

    /**
     * Clasifica por edad. Se espera un objeto de edad con years, months, days.
     *
     * @param object{years:int,months:int,days:int} $age
     */
    public static function fromAge(object $age): self
    {
        if ($age->years === 0 && $age->months === 0 && $age->days <= 28) {
            return self::RECIEN_NACIDO;
        }

        if ($age->years === 0 && $age->months < 12) {
            return self::NEONATO;
        }

        if ($age->years < 10) {
            return self::NINO;
        }

        if ($age->years < 18) {
            return self::ADOLESCENTE;
        }

        if ($age->years >= 65) {
            return self::ADULTO_MAYOR;
        }

        return self::ADULTO;
    }
}
