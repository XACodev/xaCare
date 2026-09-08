<?php

namespace App\Support;

class NameFormatter
{
    /**
     * Partículas de nombres/apellidos en español que van en minúscula salvo que sean
     * la primera palabra (ej. "Juan de la Cruz", pero "De la Cruz" si es lo primero).
     */
    private const LOWERCASE_PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y'];

    public static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ($value === '') {
            return null;
        }

        $words = explode(' ', $value);

        $formatted = array_map(function (string $word, int $index) {
            $lower = mb_strtolower($word);

            if ($index > 0 && in_array($lower, self::LOWERCASE_PARTICLES, true)) {
                return $lower;
            }

            return self::capitalizeWord($word);
        }, $words, array_keys($words));

        return implode(' ', $formatted);
    }

    /**
     * Capitaliza cada segmento de una palabra compuesta por guiones o apóstrofes
     * (ej. "maria-jose" -> "Maria-Jose", "o'brien" -> "O'Brien").
     */
    private static function capitalizeWord(string $word): string
    {
        $segments = preg_split('/([-\'])/', $word, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(
            fn (string $segment) => in_array($segment, ['-', "'"], true)
                ? $segment
                : mb_strtoupper(mb_substr($segment, 0, 1)).mb_strtolower(mb_substr($segment, 1)),
            $segments,
        ));
    }
}
