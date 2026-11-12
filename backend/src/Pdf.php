<?php
declare(strict_types=1);

namespace Nacenime;

use Dompdf\{Dompdf, Options};

/** PDF so zhrnutím odhadu. Farby a tón podľa README dizajnu; písmo DejaVu Sans (v dompdf, zvláda slovenskú diakritiku). */
final class Pdf
{
    /** @param array<string, mixed> $o riadok z tabuľky odhady */
    public function vytvor(array $o, string $makler = ''): string
    {
        $h = fn(mixed $x): string => htmlspecialchars((string) $x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $eur = fn(int $n): string => number_format($n, 0, ',', "\u{00a0}") . "\u{00a0}€";
        $adresa = trim(implode(', ', array_filter([$o['adresa'] ?? '', trim(($o['psc'] ?? '') . ' ' . ($o['obec'] ?? ''))])));
        $ok = ($o['vysledok'] ?? '') === 'ok' && $o['cena_od'] !== null;
        $zhrnutie = $h($adresa) . '. ' . $h($o['typ'] ?? '') . ' ' . $h(rtrim(rtrim(number_format((float) $o['vymera'], 1, ',', ''), '0'), ',')) . ' m²';
        $hlavne = $ok
            ? '<div class="cena">' . $h($eur((int) $o['cena_od'])) . ' až ' . $h($eur((int) $o['cena_do'])) . '</div><p>' . $h($o['veta'] ?? '') . '</p>'
            : '<div class="cena mala">Cenu vám povie maklér</div><p>Na odhad tu máme málo predajov. Maklér vám povie cenu po obhliadke.</p>';
        $html = <<<HTML
<!doctype html><html lang="sk"><head><meta charset="utf-8"><style>
@page { margin: 48px 56px; }
body { font-family: "DejaVu Sans", sans-serif; color: #11122f; font-size: 12px; line-height: 1.5; }
.logo { color: #272361; font-weight: bold; font-size: 18px; }
h1 { color: #272361; font-size: 22px; margin: 28px 0 6px; }
.cena { color: #272361; font-size: 34px; font-weight: bold; margin: 18px 0 10px; }
.cena.mala { font-size: 24px; }
.poznamka { color: #50516a; font-size: 10px; margin-top: 40px; border-top: 1px solid #272361; padding-top: 10px; }
</style></head><body>
<div class="logo">nacenime.sk</div>
<h1>Orientačný odhad ceny</h1>
<p>{$zhrnutie}</p>
{$hlavne}
<p class="poznamka">Orientačný, nezáväzný odhad. Nejde o znalecký posudok.<br>Vygenerované {$h(date('d.m.Y'))}. Prevádzkuje NajBývanie, s.r.o.</p>
</body></html>
HTML;
        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $opt->set('isPhpEnabled', false);
        $d = new Dompdf($opt);
        $d->loadHtml($html, 'UTF-8');
        $d->setPaper('A4');
        $d->render();
        return (string) $d->output();
    }
}
