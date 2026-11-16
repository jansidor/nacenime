<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'Pdf', 'OdosielacPdf'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Api, Config, Odhad, OdosielacPdf, Pdf, Storage};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$st = new Storage(':memory:');
$api = new Api($odhad, new Config([]), $st);
$api->handle('POST', '/api/odhad', ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66, 'adresa' => ['ulica' => 'Bystrická cesta 12']]);

$pdf = (new Pdf())->vytvor($st->odhad(1));
$check('PDF je platný súbor', '%PDF-', substr($pdf, 0, 5));
$check('PDF pre odhad s málo dátami', '%PDF-', substr((new Pdf())->vytvor(['vysledok' => 'málo dát', 'cena_od' => null, 'cena_do' => null, 'vymera' => 50, 'typ' => 'Chata']), 0, 5));
$check('PDF escapuje HTML', '%PDF-', substr((new Pdf())->vytvor(['adresa' => '<script>x</script>', 'vysledok' => 'ok', 'cena_od' => 1000, 'cena_do' => 2000, 'vymera' => 50, 'typ' => 'Byt', 'veta' => '<b>']), 0, 5));

$api->handle('POST', '/api/kontakt/pdf', ['email' => 'a@b.sk', 'odhad_id' => 1]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'bez@odhadu.sk']);
$poslane = [];
$ok = function (string $komu, string $predmet, string $telo, string $pdf, string $nazov) use (&$poslane): void { $poslane[] = [$komu, $nazov, substr($pdf, 0, 5)]; };
$r = (new OdosielacPdf($st, new Pdf(), $ok))->spusti();
$check('odoslané + preskočené', ['odoslane' => 1, 'zlyhalo' => 0, 'preskocene' => 1], $r);
$check('príloha je PDF', [['a@b.sk', 'odhad-nacenime.pdf', '%PDF-']], $poslane);
$check('druhý beh nič neposiela', ['odoslane' => 0, 'zlyhalo' => 0, 'preskocene' => 0], (new OdosielacPdf($st, new Pdf(), $ok))->spusti());

$api->handle('POST', '/api/kontakt/pdf', ['email' => 'zly@smtp.sk', 'odhad_id' => 1]);
$zle = fn() => throw new RuntimeException('SMTP padlo');
$vysledky = [];
for ($i = 0; $i < 7; $i++) { $vysledky[] = (new OdosielacPdf($st, new Pdf(), $zle, 3))->spusti()['zlyhalo']; }
$check('zlyhanie sa opakuje najviac 3×', [1, 1, 1, 0, 0, 0, 0], $vysledky);
$check('po oprave sa neposiela, čo sa vzdalo', 0, (new OdosielacPdf($st, new Pdf(), $ok, 3))->spusti()['odoslane']);

// obsah PDF (HTML pred vykreslením)
$o = ['adresa' => 'Bystrická cesta 12', 'psc' => '034 01', 'obec' => 'Ružomberok', 'typ' => 'Byt', 'vymera' => 66.5, 'vysledok' => 'ok', 'cena_od' => 131000, 'cena_do' => 145000, 'veta' => 'Podľa 6 podobných predajov v okolí.'];
$html = (new Pdf('+421 900 000 001', 'test@nacenime.sk'))->html($o, 'Ján Sidor');
$check('PDF: obchodné meno, cena, veta pod cenou', [true, true, true], [str_contains($html, 'NajBývanie Ružomberok, s. r. o.'), str_contains($html, "131\u{00a0}000\u{00a0}€ až 145\u{00a0}000\u{00a0}€"), str_contains($html, Api::POZNAMKA['Byt'])]);
$check('PDF: kontakt na makléra (meno, telefón, e-mail)', [true, true, true], [str_contains($html, 'Maklér Ján Sidor'), str_contains($html, '+421 900 000 001'), str_contains($html, 'test@nacenime.sk')]);
$check('PDF: dátum v slovenskom tvare', 1, preg_match('/Vygenerované \d{1,2}\. \d{1,2}\. \d{4}\./u', $html));
$check('PDF: zástupné meno makléra sa nezobrazí', [false, true], [str_contains($p = (new Pdf())->html($o, '[MENO MAKLÉRA]'), '[MENO'), str_contains($p, 'Maklér NajBývanie')]);
$poz = (new Pdf())->html(['adresa' => null, 'psc' => null, 'obec' => 'Liptovský Mikuláš', 'typ' => 'Pozemok', 'vymera' => 800, 'vysledok' => 'málo dát', 'cena_od' => null, 'cena_do' => null, 'veta' => null]);
$check('PDF pozemok bez adresy: zhrnutie nezačína bodkou, bez rozpätia', [false, true, true], [str_contains($poz, '<p>. '), str_contains($poz, '<p>Liptovský Mikuláš. Pozemok 800 m²</p>'), str_contains($poz, 'Cenu vám povie maklér')]);
$check('PDF: HTML z adresy je escapované', false, str_contains((new Pdf())->html(['adresa' => '<b>x</b>'] + $o), '<b>x</b>'));

exit($fail > 0 ? 1 : 0);
