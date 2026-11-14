<?php
declare(strict_types=1);

namespace Nacenime;

/** Validácia vstupov. Vracia [hodnoty, chyby]; chyby sú vety pripravené pre používateľa. */
final class Validator
{
    public const VYMERA_MIN = 10;
    public const VYMERA_MAX = 2000;

    /** @param list<string> $typy @param list<string> $stavy
     *  @return array{0: array<string, mixed>, 1: array<string, string>} */
    public static function odhad(array $in, array $typy, array $stavy): array
    {
        $e = [];
        $typ = is_string($in['typ'] ?? null) ? trim($in['typ']) : '';
        if (!in_array($typ, $typy, true)) {
            $e['typ'] = 'Vyberte typ nehnuteľnosti.';
        }
        $obec = is_string($in['obec'] ?? null) ? trim($in['obec']) : '';
        if ($obec === '' || mb_strlen($obec) > 100) {
            $e['obec'] = 'Zadajte obec.';
        }
        $stav = null;
        if ($typ !== 'Pozemok') {
            $stav = is_string($in['stav'] ?? null) ? trim($in['stav']) : '';
            if (!in_array($stav, $stavy, true)) {
                $e['stav'] = 'Vyberte stav nehnuteľnosti.';
            }
        }
        $vymera = self::cislo($in['vymera'] ?? null);
        if ($vymera === null || $vymera < self::VYMERA_MIN || $vymera > self::VYMERA_MAX) {
            $e['vymera'] = 'Zadajte výmeru od 10 do 2000 m².';
        }
        return [['typ' => $typ, 'obec' => $obec, 'stav' => $stav, 'vymera' => $vymera], $e];
    }

    /** Číslo s desatinnou čiarkou alebo bodkou; medzery sa ignorujú. */
    public static function cislo(mixed $v): int|float|null
    {
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? $v : null;
        }
        if (!is_string($v)) {
            return null;
        }
        $s = str_replace([' ', "\u{00a0}", ','], ['', '', '.'], trim($v));
        return preg_match('/^\d+(\.\d+)?$/', $s) === 1 ? $s + 0 : null;
    }

    public static function email(mixed $v): ?string
    {
        $s = is_string($v) ? trim($v) : '';
        return mb_strlen($s) <= 254 && filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : null;
    }

    /** Telefón: aspoň 9 číslic, povolené sú len číslice, medzery, +, -, (), /. */
    public static function telefon(mixed $v): ?string
    {
        $s = is_string($v) ? trim($v) : '';
        if ($s === '' || mb_strlen($s) > 30 || preg_match('/^[\d\s+\-()\/]+$/', $s) !== 1) {
            return null;
        }
        return strlen(preg_replace('/\D/', '', $s)) >= 9 ? $s : null;
    }
}
