<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Výpočet odhadu ceny. Port z design_handoff_nacenime/reference/odhad.js,
 * výsledky musia byť rovnaké (pozri backend/tests).
 */
final class Odhad
{
    /** @var array<string, array<string, mixed>> */
    private array $skupiny = [];
    /** @var array<string, array{obec: string, okres: string}> */
    private array $lokality = [];
    /** @var array<string, int|float> */
    private array $prahy;
    /** @var array<string, array{0: int|float, 1: int|float}> */
    private array $zlavy;

    /** @param array<string, mixed> $data obsah nacenime-cennik.json */
    public function __construct(array $data)
    {
        foreach ($data['cennik'] as $g) {
            $this->skupiny[$g['kluc']] = $g;
        }
        foreach ($data['lokality'] as $obec => $okres) {
            $this->lokality[self::norm((string) $obec)] = ['obec' => (string) $obec, 'okres' => (string) $okres];
        }
        $this->prahy = $data['prahy'];
        $this->zlavy = $data['zlavy'];
    }

    public static function zo_suboru(string $cesta): self
    {
        $json = file_get_contents($cesta);
        if ($json === false) {
            throw new \RuntimeException("Cenník sa nedá načítať: $cesta");
        }
        return new self(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    /** Nájde obec bez ohľadu na diakritiku a veľkosť písmen. @return array{obec: string, okres: string}|null */
    public function najdiObec(string $obec): ?array
    {
        return $this->lokality[self::norm($obec)] ?? null;
    }

    /** @return list<string> */
    public function typy(): array
    {
        return array_keys($this->zlavy);
    }

    /** Stavy bez zástupného „všetky“. @return list<string> */
    public function stavy(): array
    {
        $s = array_unique(array_column($this->skupiny, 'stav'));
        return array_values(array_filter($s, fn($x) => $x !== 'všetky'));
    }

    /** Malé písmená bez diakritiky a okrajových medzier. */
    public static function norm(?string $t): string
    {
        static $mapa = [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
            'ĺ' => 'l', 'ľ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ŕ' => 'r',
            'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ý' => 'y', 'ž' => 'z',
        ];
        $t = mb_strtolower(trim($t ?? ''), 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $t = \Normalizer::normalize($t, \Normalizer::FORM_D) ?: $t;
        }
        // Rozložené znaky (základ + kombinujúci znak) aj bez intl; potom zloženia podľa tabuľky.
        return strtr(preg_replace('/\p{Mn}/u', '', $t) ?? $t, $mapa);
    }

    /** Zaokrúhlenie na tisíce, nie pod nulu (rovnako ako Math.round v JS). */
    private static function round1000(float $n): int
    {
        return (int) max(0, floor($n / 1000 + 0.5) * 1000);
    }

    /**
     * @param array{typ: string, obec: string, stav?: ?string, vymera: int|float} $v
     * @param list<string> $typyBezRozpatia
     * @return array<string, mixed>
     */
    public function odhad(array $v, array $typyBezRozpatia = []): array
    {
        $typ = $v['typ'];
        $stav = $v['stav'] ?? null;
        $vymera = $v['vymera'];
        $prazdny = ['od' => null, 'do' => null, 'pocet' => null, 'zdroj' => null, 'uroven' => null,
            'pouzity_stav' => null, 'veta' => null, 'nadpis' => null];

        if (in_array($typ, $typyBezRozpatia, true)) {
            return ['stav' => 'maklér'] + array_merge($prazdny, [
                'nadpis' => 'Cenu vám povie maklér',
                'veta' => 'Pri tomto type nehnuteľnosti rozpätie neukazujeme. Maklér si ju pozrie a povie vám cenu.',
            ]);
        }
        $lok = $this->lokality[self::norm($v['obec'])] ?? null;
        if ($lok === null || !isset($this->zlavy[$typ])) {
            return ['stav' => 'mimo regiónu'] + $prazdny;
        }
        $lokalita = ['obec' => $lok['obec'], 'okres' => 'okres ' . $lok['okres']];

        foreach ($this->poradie($stav) as [$zdroj, $uroven, $s]) {
            $g = $this->skupiny[implode('|', [$zdroj, $typ, $lokalita[$uroven], $s])] ?? null;
            if ($g === null || $g['pocet'] < $this->prahy[strtolower($zdroj)]) {
                continue;
            }
            [$zlavaMin, $zlavaMax] = $this->zlavy[$typ];
            $predaj = $zdroj === 'Predaje';
            $veta = implode(' ', array_filter([
                $predaj
                    ? "Podľa {$g['pocet']} podobných predajov v okolí."
                    : "Podľa {$g['pocet']} aktuálnych ponúk v okolí, po odpočítaní bežnej zľavy pri predaji.",
                $s === 'všetky' ? 'Bez rozlíšenia stavu.' : null,
                $uroven === 'okres' ? 'Rátané z celého okresu.' : null,
            ]));
            return [
                'stav' => 'ok',
                'od' => self::round1000($g['q1'] * $vymera - ($predaj ? 0 : $zlavaMax)),
                'do' => self::round1000($g['q3'] * $vymera - ($predaj ? 0 : $zlavaMin)),
                'pocet' => $g['pocet'], 'zdroj' => $zdroj, 'uroven' => $uroven,
                'pouzity_stav' => $s, 'veta' => $veta, 'nadpis' => null,
            ];
        }
        return ['stav' => 'málo dát'] + array_merge($prazdny, [
            'veta' => 'Maklér vám povie cenu po obhliadke.',
            'nadpis' => 'Na odhad tu máme málo predajov',
        ]);
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function poradie(?string $stav): array
    {
        $sk = fn(string $s): array => [['Predaje', 'obec', $s], ['Ponuky', 'obec', $s], ['Predaje', 'okres', $s], ['Ponuky', 'okres', $s]];
        return [...($stav !== null && $stav !== '' ? $sk($stav) : []), ...$sk('všetky')];
    }
}
