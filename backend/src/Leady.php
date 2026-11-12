<?php
declare(strict_types=1);

namespace Nacenime;

/** CSV export leadov. Hodnoty, ktoré by tabuľkový procesor vyhodnotil ako vzorec, sa neutralizujú (CSV injection). */
final class Leady
{
    /** @param list<array<string, mixed>> $riadky */
    public static function csv(array $riadky): string
    {
        $stlpce = ['id', 'vytvorene', 'druh', 'telefon', 'email', 'adresa', 'psc', 'obec', 'typ', 'vymera', 'stav', 'cena_od', 'cena_do', 'dovod', 'predstava_ceny'];
        $f = fopen('php://memory', 'w+');
        fwrite($f, "\xEF\xBB\xBF"); // BOM, aby Excel čítal diakritiku
        fputcsv($f, $stlpce, ';', '"', '\\');
        foreach ($riadky as $r) {
            fputcsv($f, array_map(fn($c) => self::bezpecne($r[$c] ?? ''), $stlpce), ';', '"', '\\');
        }
        rewind($f);
        return (string) stream_get_contents($f);
    }

    public static function bezpecne(mixed $v): string
    {
        $s = (string) $v;
        return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'" . $s : $s;
    }
}
