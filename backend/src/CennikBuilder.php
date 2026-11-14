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
            if ($m2 < self::CENA_M2_MIN || $m2 > self::CENA_M2_MAX) {
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
