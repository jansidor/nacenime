<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Podrobnosti pri dome: podlažia, pozemok, vedľajšie stavby a bazén.
 * Podlahová plocha = prízemie + poschodia + podkrovie + 30 % pivnice (cenu rátame z tejto plochy).
 * Stavby a bazén sú orientačné paušálne príplatky v eurách (úprava len tu).
 */
final class Dom
{
    public const VAHA_PIVNICE = 0.3;
    public const PODLAZIA = [
        'prizemie' => 'Prízemie', 'poschodie1' => '1. poschodie', 'poschodie2' => '2. poschodie',
        'poschodie3' => '3. poschodie', 'podkrovie' => 'Podkrovie', 'pivnica' => 'Pivnica',
    ];
    public const VLASTNICTVO = ['ano' => 'dom aj pozemok sú vo vlastníctve', 'bez' => 'dom bez pozemku', 'neviem' => 'vlastníctvo pozemku nie je známe'];
    /** Príplatky v €. */
    public const STAVBY = [
        'garaz' => ['Garáž alebo prístrešok', 5000], 'domcek' => ['Záhradný domček alebo chatka', 3000],
        'humno' => ['Humno alebo hospodárska stavba', 2000], 'studna' => ['Studňa alebo čistička', 1000], 'ine' => ['Iná stavba', 0],
    ];
    public const BAZEN = ['maly' => ['Bazén do 15 m²', 1500], 'stredny' => ['Bazén 15 až 30 m²', 3000], 'velky' => ['Bazén nad 30 m²', 5000]];
    public const PLOCHA_MAX = 2000;

    /**
     * @param array<string, mixed> $in
     * @return array{0: ?array<string, mixed>, 1: array<string, string>}
     */
    public static function spracuj(array $in): array
    {
        $e = [];
        $pl = is_array($in['podlazia'] ?? null) ? $in['podlazia'] : [];
        $plochy = [];
        foreach (array_keys(self::PODLAZIA) as $k) {
            $raw = $pl[$k] ?? null;
            if ($raw === null || $raw === '' || $raw === 0 || $raw === '0') {
                $plochy[$k] = 0.0;
                continue;
            }
            $n = Validator::cislo($raw);
            if ($n === null || $n < 0 || $n > self::PLOCHA_MAX) {
                $e[$k] = 'Zadajte výmeru od 1 do 2000 m².';
                $plochy[$k] = 0.0;
                continue;
            }
            $plochy[$k] = (float) $n;
        }
        if (!isset($e['prizemie']) && $plochy['prizemie'] < Validator::VYMERA_MIN) {
            $e['prizemie'] = 'Zadajte výmeru prízemia, aspoň 10 m².';
        }
        $plocha = round(array_sum($plochy) - $plochy['pivnica'] + self::VAHA_PIVNICE * $plochy['pivnica'], 1);
        if ($plocha > Validator::VYMERA_MAX) {
            $e['prizemie'] = 'Súčet výmer je príliš veľký (najviac 2000 m²).';
        }

        $vlast = is_string($in['vlastnictvo'] ?? null) ? $in['vlastnictvo'] : 'neviem';
        if (!isset(self::VLASTNICTVO[$vlast])) {
            $e['vlastnictvo'] = 'Vyberte možnosť.';
        }
        $pozemok = null;
        if (($in['pozemok'] ?? null) !== null && $in['pozemok'] !== '') {
            $pozemok = Validator::cislo($in['pozemok']);
            if ($pozemok === null || $pozemok < 1 || $pozemok > 1000000) {
                $e['pozemok'] = 'Zadajte výmeru pozemku v m².';
                $pozemok = null;
            }
        }

        $stavby = [];
        foreach (is_array($in['stavby'] ?? null) ? $in['stavby'] : [] as $k) {
            if (is_string($k) && isset(self::STAVBY[$k]) && !in_array($k, $stavby, true)) {
                $stavby[] = $k;
            }
        }
        $bazen = $in['bazen'] ?? null;
        if ($bazen !== null && $bazen !== '' && $bazen !== 'nie') {
            if (!is_string($bazen) || !isset(self::BAZEN[$bazen])) {
                $e['bazen'] = 'Vyberte veľkosť bazéna.';
                $bazen = null;
            }
        } else {
            $bazen = null;
        }
        $ine = '';
        if (in_array('ine', $stavby, true) && is_string($in['ine'] ?? null)) {
            $ine = mb_substr(trim((string) preg_replace('/[\p{C}]+/u', ' ', $in['ine'])), 0, 100);
        }
        if ($e) {
            return [null, $e];
        }

        $polozky = [];
        foreach ($stavby as $k) {
            if (self::STAVBY[$k][1] > 0) {
                $polozky[] = ['kluc' => $k, 'nazov' => self::STAVBY[$k][0], 'suma' => self::STAVBY[$k][1]];
            }
        }
        if ($bazen !== null) {
            $polozky[] = ['kluc' => 'bazen_' . $bazen, 'nazov' => self::BAZEN[$bazen][0], 'suma' => self::BAZEN[$bazen][1]];
        }
        $priplatok = array_sum(array_column($polozky, 'suma'));

        $casti = [];
        foreach (self::PODLAZIA as $k => $nazov) {
            if ($plochy[$k] > 0) {
                $casti[] = $nazov . ' ' . self::m2($plochy[$k]);
            }
        }
        $popis = 'Podlažia: ' . implode(', ', $casti) . ' (rátaná plocha ' . self::m2($plocha) . ')'
            . '. Pozemok: ' . self::VLASTNICTVO[$vlast] . ($pozemok !== null ? ', ' . self::m2((float) $pozemok) : '');
        $nazvyStavieb = array_map(fn($k) => $k === 'ine' && $ine !== '' ? 'iná stavba (' . $ine . ')' : mb_strtolower(self::STAVBY[$k][0]), $stavby);
        if ($bazen !== null) {
            $nazvyStavieb[] = mb_strtolower(self::BAZEN[$bazen][0]);
        }
        $popis .= '. Na pozemku: ' . ($nazvyStavieb ? implode(', ', $nazvyStavieb) : 'bez ďalších stavieb') . '.';

        return [[
            'vymera' => $plocha, 'priplatok' => $priplatok, 'priplatky' => $polozky, 'popis' => $popis,
            'vlastnictvo' => $vlast,
        ], []];
    }

    private static function m2(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',') . ' m²';
    }
}
