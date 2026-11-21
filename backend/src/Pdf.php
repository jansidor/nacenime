<?php
declare(strict_types=1);

namespace Nacenime;

use Dompdf\{Dompdf, Options};

/** PDF so zhrnutím odhadu. Farby a tón podľa README dizajnu; písmo DejaVu Sans (v dompdf, zvláda slovenskú diakritiku). */
final class Pdf
{
    /** Kontakt v PDF (rovnaký ako na stránke Kontakt). Dá sa zmeniť v .env: NACENIME_KONTAKT_TELEFON, NACENIME_KONTAKT_EMAIL. */
    public function __construct(private string $telefon = '+421 951 161 561', private string $email = 'info@nacenime.sk') {}

    /** @param array<string, mixed> $o riadok z tabuľky odhady */
    public function vytvor(array $o, string $makler = ''): string
    {
        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $opt->set('isPhpEnabled', false);
        $d = new Dompdf($opt);
        $d->loadHtml($this->html($o, $makler), 'UTF-8');
        $d->setPaper('A4');
        $d->render();
        return (string) $d->output();
    }

    /** HTML, z ktorého sa PDF vykreslí (samostatne kvôli testom obsahu). @param array<string, mixed> $o */
    public function html(array $o, string $makler = ''): string
    {
        $h = fn(mixed $x): string => htmlspecialchars((string) $x, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $eur = fn(int|float $n): string => number_format((float) $n, 0, ',', "\u{00a0}") . "\u{00a0}€";
        $m2 = fn(mixed $n): string => rtrim(rtrim(number_format((float) $n, 1, ',', "\u{00a0}"), '0'), ',') . "\u{00a0}m²";
        $adresa = trim(implode(', ', array_filter([$o['adresa'] ?? '', trim(($o['psc'] ?? '') . ' ' . ($o['obec'] ?? ''))])));
        $ok = ($o['vysledok'] ?? '') === 'ok' && $o['cena_od'] !== null;
        $typ = (string) ($o['typ'] ?? '');
        $jeDom = !empty($o['dom_popis']);

        // Údaje o nehnuteľnosti ako tabuľka (štítok, hodnota)
        $riadky = [['Nehnuteľnosť', $h($typ)], ['Adresa', $h($adresa)]];
        if ($jeDom && preg_match('/^Podlažia: (.*?) \(rátaná plocha (.*?)\)\. Pozemok: (.*?)\. Na pozemku: (.*)\.$/su', (string) $o['dom_popis'], $m)) {
            $riadky[] = ['Podlažia', $h($m[1])];
            $riadky[] = ['Rátaná plocha', $h($m[2]) . ' (pivnica sa počíta z 30 %)'];
            $riadky[] = ['Pozemok', $h(ucfirst($m[3]))];
            $riadky[] = ['Na pozemku', $h(ucfirst($m[4]))];
        } else {
            $riadky[] = [$typ === 'Pozemok' ? 'Výmera pozemku' : 'Výmera', $h($m2($o['vymera'] ?? 0))];
            if ($jeDom) {
                $riadky[] = ['Podrobnosti', $h($o['dom_popis'])];
            }
        }
        if (!empty($o['stav']) && $typ !== 'Pozemok') {
            $riadky[] = ['Stav', $h($o['stav'])];
        }
        $riadky = array_values(array_filter($riadky, fn($r) => trim(strip_tags((string) $r[1])) !== ''));
        $tabulka = '';
        foreach ($riadky as [$k, $v]) {
            $tabulka .= '<tr><td class="k">' . $k . '</td><td class="v">' . $v . '</td></tr>';
        }

        $poznamka = $ok ? (Api::POZNAMKA[$typ] ?? '') : '';
        if ($ok) {
            $od = (int) $o['cena_od'];
            $do = (int) $o['cena_do'];
            $siroke = $od > 0 && $do / $od > 1.6;
            $zaMeter = !$jeDom && $typ !== 'Pozemok' && (float) $o['vymera'] > 0
                ? '<p class="zam">Za m²: ' . $h($eur(round($od / (float) $o['vymera'], -1))) . ' až ' . $h($eur(round($do / (float) $o['vymera'], -1))) . '</p>' : '';
            $hlavne = '<div class="cena-box"><div class="stitok">Orientačné cenové rozpätie</div>'
                . '<div class="cena">' . $h($eur($od)) . ' až ' . $h($eur($do)) . '</div>'
                . '<table class="skala"><tr><td class="s1"></td><td class="s2"></td><td class="s3"></td></tr></table>'
                . $zaMeter . '</div>'
                . '<h2>Z čoho sme rátali</h2><p>' . $h($o['veta'] ?? '') . '</p>'
                . ($poznamka !== '' ? '<p>' . $h($poznamka) . '</p>' : '')
                . ($siroke ? '<p class="upoz">Rozpätie je pri tejto nehnuteľnosti široké, lebo sa v okolí ceny výrazne líšia. Presnejšiu cenu určí až obhliadka.</p>' : '');
        } else {
            $hlavne = '<div class="cena-box"><div class="cena mala">Cenu vám povie maklér</div></div>'
                . '<p>Na odhad tu máme málo predajov. Maklér vám povie cenu po obhliadke.</p>';
        }
        $meno = trim($makler) !== '' && !str_contains($makler, '[') ? trim($makler) : '';
        $makleriText = $meno !== '' ? 'Maklér ' . $h($meno) : 'Maklér NajBývanie';
        $kontakt = '<div class="kontakt"><div class="kontakt-nadpis">Chcete presnú cenu?</div>'
            . '<table class="kroky"><tr><td><b>1.</b> Dohodnete si krátku obhliadku.</td><td><b>2.</b> Dostanete jednu konkrétnu cenu.</td><td><b>3.</b> Rozhodnete sa, bez záväzkov.</td></tr></table>'
            . '<p>' . $makleriText . ' si nehnuteľnosť pozrie a povie jednu cenu.</p>'
            . '<p class="spojenie">' . $h($this->telefon) . ' &nbsp;·&nbsp; ' . $h($this->email) . ' &nbsp;·&nbsp; nacenime.sk</p></div>';
        $datum = $h(date('j. n. Y'));
        $cislo = isset($o['id']) ? '<br>Odhad č. ' . $h(str_pad((string) (int) $o['id'], 6, '0', STR_PAD_LEFT)) : '';
        $html = <<<HTML
<!doctype html><html lang="sk"><head><meta charset="utf-8"><style>
@page { margin: 0; }
body { font-family: "DejaVu Sans", sans-serif; color: #11122f; font-size: 11.5px; line-height: 1.55; margin: 0; }
.hlava { background: #11122f; color: #ffffff; padding: 28px 56px 24px; }
.hlava table { width: 100%; border-collapse: collapse; }
.logo { font-size: 20px; font-weight: bold; color: #ffffff; }
.logo span { color: #7fe0c8; }
.meta { text-align: right; font-size: 10px; color: #c9c8e6; }
.pas { height: 5px; background: #7fe0c8; }
.telo { padding: 26px 56px 0; }
h1 { color: #272361; font-size: 21px; margin: 0 0 14px; }
h2 { color: #272361; font-size: 13px; margin: 20px 0 4px; }
table.data { width: 100%; border-collapse: collapse; border-top: 1px solid #dcd9d0; }
table.data td { padding: 6px 0; border-bottom: 1px solid #dcd9d0; vertical-align: top; }
td.k { width: 26%; color: #50516a; font-size: 10.5px; }
td.v { font-weight: bold; }
.cena-box { margin-top: 22px; padding: 18px 22px; background: #f6f4ef; border-left: 5px solid #272361; }
.stitok { font-size: 10px; letter-spacing: 1px; text-transform: uppercase; color: #50516a; }
.cena { color: #272361; font-size: 30px; font-weight: bold; margin: 6px 0 10px; }
.cena.mala { font-size: 22px; }
table.skala { width: 100%; border-collapse: collapse; }
table.skala td { height: 8px; padding: 0; }
.s1, .s3 { background: #dcd9d0; width: 33%; }
.s2 { background: #7fe0c8; width: 34%; }
.zam { margin: 8px 0 0; color: #50516a; font-size: 10.5px; }
.upoz { padding: 8px 12px; background: #f6f4ef; color: #272361; font-size: 10.5px; }
.kontakt { margin-top: 24px; padding: 16px 20px; border: 1.5px solid #272361; }
.kontakt-nadpis { color: #272361; font-weight: bold; font-size: 14px; }
.kontakt p { margin: 8px 0 0; }
table.kroky { width: 100%; border-collapse: collapse; margin-top: 8px; }
table.kroky td { width: 33%; padding-right: 10px; font-size: 10.5px; color: #50516a; vertical-align: top; }
.spojenie { color: #272361; font-weight: bold; }
.paticka { position: fixed; left: 56px; right: 56px; bottom: 30px; border-top: 1px solid #272361; padding-top: 8px; color: #50516a; font-size: 9px; line-height: 1.5; }
</style></head><body>
<div class="hlava"><table><tr><td class="logo">nacenime<span>.sk</span></td><td class="meta">Vygenerované {$datum}.{$cislo}</td></tr></table></div>
<div class="pas"></div>
<div class="telo">
<h1>Orientačný odhad ceny nehnuteľnosti</h1>
<table class="data">{$tabulka}</table>
{$hlavne}
{$kontakt}
</div>
<div class="paticka">Orientačný, nezáväzný odhad. Nejde o znalecký posudok a nie je ponukou na uzavretie zmluvy. Rozpätie vychádza z predajov a ponúk v okolí a skutočná cena sa môže líšiť.<br>Prevádzkuje NajBývanie Ružomberok, s. r. o. &nbsp;·&nbsp; nacenime.sk</div>
</body></html>
HTML;
        return $html;
    }
}
