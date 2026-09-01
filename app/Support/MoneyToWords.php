<?php

namespace App\Support;

use NumberFormatter;

class MoneyToWords
{
    /**
     * Deletrea un monto en quetzales con formato legal de cheque, ej:
     * "Dos mil seiscientos cincuenta y cinco quetzales con 50/100".
     */
    public static function spell(float $amount): string
    {
        $quetzales = (int) floor($amount);
        $cents = (int) round(($amount - $quetzales) * 100);

        $formatter = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $spelled = $formatter->format($quetzales);

        $unit = $quetzales === 1 ? 'quetzal' : 'quetzales';

        if ($quetzales === 1) {
            $spelled = 'un';
        }

        $spelled = ucfirst($spelled);

        return sprintf('%s %s con %02d/100', $spelled, $unit, $cents);
    }
}
