<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Zostaví cenník (kvartily €/m²) zo surových predajov a ponúk.
 * Metóda kvartilov: lineárna interpolácia (Excel QUARTILE.INC, numpy "linear"). Pôvodná metóda cenníka nie je
 * dokumentovaná, preto je to predpoklad na potvrdenie. Skupiny s menej ako MIN_POCET záznamami sa nevytvárajú
 * (pôvodný cenník neobsahuje skupinu s menej ako 3 záznamami).
 */
final class CennikBuilder
{
    public const MIN_POCET = 3;
    public const CENA_M2_MIN = 100;
    public const CENA_M2_MAX = 20000;
    /** Pozemky sú lacnejšie (v cenníku bežne 20 až 150 €/m²), preto vlastné hranice pre varovanie. */
    public const POZEMOK_M2_MIN = 5;
    public const POZEMOK_M2_MAX = 1000;

    /**
     * @param list<array<string, string>> $riadky stĺpce: zdroj, typ, obec, stav, cena, plocha
     * @param array<string, string> $lokality obec => okres
     * @param array<string, mixed> $typyZlavy kľúče sú povolené typy
     * @return array{cennik: list<array<string, mixed>>, preskocene: list<string>, varovania: list<string>}
     */
    public static function zostav(array $riadky, array $lokality, array $typyZlavy, array $stavy): array
    {
        $okresDo = [];
        foreach ($lokality as $o => $ok) { $okresDo[Odhad::norm($o)] = [$o, $ok]; }
        $skupiny = [];
        $preskocene = [];
        $varovania = [];
        foreach ($riadky as $i => $r) {
            $c = $i + 2; // číslo riadku v CSV (hlavička = 1)
            $zdroj = ucfirst(strtolower(trim($r['zdroj'] ?? '')));
            $typ = self::typ(trim($r['typ'] ?? ''), $typyZlavy);
            $loc = $okresDo[Odhad::norm($r['obec'] ?? '')] ?? null;
            $cena = Validator::cislo($r['cena'] ?? null);
            $plocha = Validator::cislo($r['plocha'] ?? null);
            $stav = trim($r['stav'] ?? '');
            if (!in_array($zdroj, ['Predaje', 'Ponuky'], true) || $typ === null || $loc === null || $cena === null || $plocha === null || $plocha <= 0 || $cena <= 0) {
                $preskocene[] = "riadok $c: neplatný zdroj, typ, obec, cena alebo plocha";
                continue;
            }
            if ($typ !== 'Pozemok' && !in_array($stav, $stavy, true)) {
                $preskocene[] = "riadok $c: neznámy stav '$stav'";
                continue;
            }
            $m2 = $cena / $plocha;
            [$min, $max] = $typ === 'Pozemok' ? [self::POZEMOK_M2_MIN, self::POZEMOK_M2_MAX] : [self::CENA_M2_MIN, self::CENA_M2_MAX];
            if ($m2 < $min || $m2 > $max) {
                $varovania[] = sprintf('riadok %d: %d €/m² je podozrivé (cena %s, plocha %s), záznam zahrnutý', $c, $m2, $cena, $plocha);
            }
            [$obec, $okres] = $loc;
            $stavy_ = $typ === 'Pozemok' ? ['všetky'] : [$stav, 'všetky'];
            foreach ([['obec', $obec], ['okres', "okres $okres"]] as [$uroven, $lokalita]) {
                foreach ($stavy_ as $s) {
                    $k = implode('|', [$zdroj, $typ, $lokalita, $s]);
                    $skupiny[$k] ??= ['kluc' => $k, 'zdroj' => $zdroj, 'typ' => $typ, 'uroven' => $uroven, 'lokalita' => $lokalita, 'stav' => $s, 'hodnoty' => []];
                    $skupiny[$k]['hodnoty'][] = $m2;
                }
            }
        }
        $out = [];
        foreach ($skupiny as $g) {
            $h = $g['hodnoty'];
            if (count($h) < self::MIN_POCET) { continue; }
            sort($h);
            unset($g['hodnoty']);
            $out[] = $g + ['pocet' => count($h), 'q1' => (int) round(self::kvantil($h, 0.25)), 'median' => (int) round(self::kvantil($h, 0.5)), 'q3' => (int) round(self::kvantil($h, 0.75))];
        }
        usort($out, fn($a, $b) => [$a['zdroj'], $a['typ'], $a['uroven'] === 'obec' ? 0 : 1, $a['lokalita'], $a['stav']] <=> [$b['zdroj'], $b['typ'], $b['uroven'] === 'obec' ? 0 : 1, $b['lokalita'], $b['stav']]);
        return ['cennik' => $out, 'preskocene' => $preskocene, 'varovania' => $varovania];
    }

    /** @param list<float|int> $zoradene vzostupne @return float */
    /** Okresy, ktorých sa skupiny týkajú (obec cez zoznam lokalít, okres z názvu „okres X“).
     * @param list<array<string, mixed>> $skupiny @param array<string, string> $lokality @return list<string> */
    public static function okresy(array $skupiny, array $lokality): array
    {
        $o = [];
        foreach ($skupiny as $g) {
            $l = (string) $g['lokalita'];
            $ok = $g['uroven'] === 'okres' ? substr($l, strlen('okres ')) : ($lokality[$l] ?? null);
            if ($ok !== null) { $o[$ok] = true; }
        }
        $o = array_keys($o);
        sort($o);
        return $o;
    }

    /**
     * Doplnenie: v starom cenníku nahradí len okresy, ktoré obsahuje nový vstup. Ostatné okresy ostanú nedotknuté.
     * @param list<array<string, mixed>> $stary @param list<array<string, mixed>> $novy @param array<string, string> $lokality
     * @return list<array<string, mixed>>
     */
    public static function doplnOkresy(array $stary, array $novy, array $lokality): array
    {
        $nahradit = array_flip(self::okresy($novy, $lokality));
        $ostava = array_values(array_filter($stary, fn($g) => !isset($nahradit[self::okresy([$g], $lokality)[0] ?? ''])));
        return array_merge($ostava, $novy); // poradie starých skupín ostane, nové sa pridajú na koniec
    }

    public static function kvantil(array $zoradene, float $p): float
    {
        $n = count($zoradene);
        $pos = ($n - 1) * $p;
        $lo = (int) floor($pos);
        $hi = min($lo + 1, $n - 1);
        return $zoradene[$lo] + ($pos - $lo) * ($zoradene[$hi] - $zoradene[$lo]);
    }

    /** JSON v rovnakom tvare ako existujúci cenník: odsadenie 1 medzera, bez escapovania diakritiky. */
    public static function json(array $data): string
    {
        $j = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return preg_replace_callback('/^( {4})+/m', fn($m) => str_repeat(' ', intdiv(strlen($m[0]), 4)), $j);
    }

    /** @param array<string, mixed> $typyZlavy */
    private static function typ(string $t, array $typyZlavy): ?string
    {
        foreach (array_keys($typyZlavy) as $povoleny) {
            if (Odhad::norm($povoleny) === Odhad::norm($t)) { return (string) $povoleny; }
        }
        return null;
    }
}
