<?php

namespace App\Support;

class NameFormatter
{
    /**
     * Partículas de nombres/apellidos en español que van en minúscula salvo que sean
     * la primera palabra (ej. "Juan de la Cruz", pero "De la Cruz" si es lo primero).
     */
    private const LOWERCASE_PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y'];

    /**
     * `$preserveAcronyms` es opt-in y por defecto queda apagado: para nombres de
     * persona (Patient, User) una palabra toda en mayúsculas casi siempre es alguien
     * escribiendo con el bloqueo de mayúsculas activado, no una sigla — por eso el
     * comportamiento por defecto sigue siendo el de siempre (title-case sin excepciones).
     * Actívalo solo para catálogos/nombres institucionales (ej. ProcedureType), donde
     * una palabra en mayúsculas sí suele ser una sigla deliberada (ej. "CSTP", "QA").
     */
    public static function titleCase(?string $value, bool $preserveAcronyms = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ($value === '') {
            return null;
        }

        $words = explode(' ', $value);

        $formatted = array_map(function (string $word, int $index) use ($preserveAcronyms) {
            $lower = mb_strtolower($word);

            if ($index > 0 && in_array($lower, self::LOWERCASE_PARTICLES, true)) {
                return $lower;
            }

            if ($preserveAcronyms && self::isAcronym($word)) {
                return $word;
            }

            return self::capitalizeWord($word);
        }, $words, array_keys($words));

        return implode(' ', $formatted);
    }

    /**
     * Longitud máxima de una palabra para considerarla sigla. Las siglas reales
     * (CSTP, QA, UCI, IC10) son cortas; una palabra larga toda en mayúsculas es casi
     * siempre alguien escribiendo con el bloqueo de mayúsculas activado, no una sigla.
     */
    private const MAX_ACRONYM_LENGTH = 5;

    /**
     * Una palabra corta que ya viene completamente en mayúsculas en el texto original
     * se trata como una sigla deliberada (ej. "CSTP", "QA") y se preserva tal cual, en
     * vez de aplicarle title-case. Es el mismo criterio que usan los HIS/EHR grandes:
     * no intentan adivinar qué es una sigla, solo respetan las mayúsculas ya escritas
     * en palabras lo bastante cortas como para serlo.
     */
    private static function isAcronym(string $word): bool
    {
        return mb_strlen($word) > 1
            && mb_strlen($word) <= self::MAX_ACRONYM_LENGTH
            && preg_match('/\p{L}/u', $word) === 1
            && mb_strtoupper($word) === $word;
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
