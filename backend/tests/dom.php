<?php
declare(strict_types=1);
foreach (['Config', 'Odhad', 'Validator', 'Dom', 'Storage', 'Adresy', 'Api', 'Leady', 'Oznamovac'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Adresy, Api, Config, Dom, Odhad, Storage};

$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$config = new Config([]);
$st = new Storage(':memory:');
$api = new Api($odhad, $config, $st, new Adresy($odhad, $config));
$zaklad = ['typ' => 'Dom', 'obec' => 'Ružomberok', 'stav' => 'Pôvodný'];
$dom = fn(array $d) => ['dom' => $d + ['podlazia' => ['prizemie' => 80], 'vlastnictvo' => 'ano']] + $zaklad;

// Plocha: prízemie + poschodia + podkrovie + 30 % pivnice
[$p, $e] = Dom::spracuj(['podlazia' => ['prizemie' => '80', 'poschodie1' => '60', 'pivnica' => '40'], 'vlastnictvo' => 'ano']);
$check('plocha = 80 + 60 + 0,3 × 40', [152.0, 0, []], [$p['vymera'], $p['priplatok'], $e]);

// Bez podrobností sa dom počíta ako doteraz
$stary = $api->handle('POST', '/api/odhad', $zaklad + ['vymera' => 152]);
$novy = $api->handle('POST', '/api/odhad', $dom(['podlazia' => ['prizemie' => 80, 'poschodie1' => 60, 'pivnica' => 40]]));
$check('rovnaká cena ako pri výmere 152 m² (bez príplatkov)', [200, $stary[1]['od'], $stary[1]['do'], 152.0, 0], [$novy[0], $novy[1]['od'], $novy[1]['do'], $novy[1]['plocha'], $novy[1]['priplatok']]);

// Príplatky
$s = $api->handle('POST', '/api/odhad', $dom(['podlazia' => ['prizemie' => 80, 'poschodie1' => 60, 'pivnica' => 40], 'bazen' => 'stredny', 'stavby' => ['garaz', 'ine'], 'ine' => 'altánok']));
$check('príplatok: bazén 3 000 + garáž 5 000', 8000, $s[1]['priplatok']);
$check('cena sa zvýši o príplatok (po zaokrúhlení na tisíce)', [(int) (round(($stary[1]['od'] + 8000) / 1000) * 1000), (int) (round(($stary[1]['do'] + 8000) / 1000) * 1000)], [$s[1]['od'], $s[1]['do']]);
$check('položky príplatkov', ['garaz', 'bazen_stredny'], array_column($s[1]['priplatky'], 'kluc'));
foreach (['maly' => 1500, 'stredny' => 3000, 'velky' => 5000] as $b => $suma) {
    $check("bazén $b = $suma €", $suma, $api->handle('POST', '/api/odhad', $dom(['bazen' => $b]))[1]['priplatok']);
}
$check('iná stavba je bez príplatku', 0, $api->handle('POST', '/api/odhad', $dom(['stavby' => ['ine']]))[1]['priplatok']);
$check('neznáma stavba sa ignoruje', 0, $api->handle('POST', '/api/odhad', $dom(['stavby' => ['hrad', ['x']]]))[1]['priplatok']);

// Vlastníctvo
$check('dom bez pozemku: upozornenie', true, isset($api->handle('POST', '/api/odhad', $dom(['vlastnictvo' => 'bez']))[1]['upozornenie']));
$check('dom na vlastnom pozemku: bez upozornenia', false, isset($api->handle('POST', '/api/odhad', $dom([]))[1]['upozornenie']));

// Chyby
$chyba = fn(array $d) => $api->handle('POST', '/api/odhad', ['dom' => $d] + $zaklad);
$check('chýba prízemie', [422, true], [($x = $chyba(['podlazia' => ['poschodie1' => 50], 'vlastnictvo' => 'ano']))[0], isset($x[1]['chyby']['prizemie'])]);
$check('písmená vo výmere poschodia', [422, true], [($x = $chyba(['podlazia' => ['prizemie' => 80, 'poschodie2' => 'abc'], 'vlastnictvo' => 'ano']))[0], isset($x[1]['chyby']['poschodie2'])]);
$check('zlé vlastníctvo', 422, $chyba(['podlazia' => ['prizemie' => 80], 'vlastnictvo' => 'hm'])[0]);
$check('zlý bazén', 422, $chyba(['podlazia' => ['prizemie' => 80], 'vlastnictvo' => 'ano', 'bazen' => 'obrovsky'])[0]);
$check('súčet nad 2000 m²', 422, $chyba(['podlazia' => ['prizemie' => 1500, 'poschodie1' => 1500], 'vlastnictvo' => 'ano'])[0]);

// Ostatné typy nie sú dotknuté
$check('byt s poľom dom sa ignoruje', [200, 66.0], [($x = $api->handle('POST', '/api/odhad', ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Pôvodný', 'vymera' => 66, 'dom' => ['podlazia' => ['prizemie' => 500]]]))[0], (float) $st->odhad($x[1]['odhad_id'])['vymera']]);

// Uloženie a lead
$id = $api->handle('POST', '/api/odhad', $dom(['podlazia' => ['prizemie' => 80, 'poschodie1' => 60, 'pivnica' => 40], 'pozemok' => '600', 'bazen' => 'maly', 'stavby' => ['domcek']]))[1]['odhad_id'];
$popis = (string) $st->odhad($id)['dom_popis'];
$check('popis v databáze', [true, true, true, true], [str_contains($popis, '1. poschodie 60 m²'), str_contains($popis, 'rátaná plocha 152 m²'), str_contains($popis, '600 m²'), str_contains($popis, 'bazén do 15 m²')]);
$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0900123456', 'odhad_id' => $id]);
$lead = $st->cakajuceOznamenia(5)[0] ?? [];
[, $telo] = \Nacenime\Oznamovac::sprava($lead);
$check('e-mail makléra obsahuje popis domu', true, str_contains($telo, 'Dom: Podlažia:'));

echo $fail ? "\n$fail zlyhaní\n" : "\nvšetko prešlo\n";
exit($fail ? 1 : 0);
