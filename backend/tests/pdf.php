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

exit($fail > 0 ? 1 : 0);
