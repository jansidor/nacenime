<?php
declare(strict_types=1);
// Zostaví cenník zo surových predajov a ponúk.
// Použitie: php bin/zostav-cennik.php vstup.csv [--zapis]
//   vstup.csv: hlavička zdroj,typ,obec,stav,cena,plocha (oddeľovač čiarka alebo bodkočiarka)
//   zdroj = Predaje | Ponuky; typ = Byt|Apartmán|Dom|Chata|Pozemok; stav (pri pozemku prázdny); plocha v m² (pozemok: plocha pozemku)
//   Bez --zapis sa len vypíše súhrn (nič sa nemení). S --zapis sa prepíše sekcia `cennik` aj `vytvorene` v cenníku.
require __DIR__ . '/../src/Odhad.php';
require __DIR__ . '/../src/Validator.php';
require __DIR__ . '/../src/CennikBuilder.php';

use Nacenime\CennikBuilder;

$vstup = $argv[1] ?? '';
$zapis = in_array('--zapis', $argv, true);
is_file($vstup) || exit("Použitie: php bin/zostav-cennik.php vstup.csv [--zapis]\n");
$cesta = __DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json';
$d = json_decode((string) file_get_contents($cesta), true, 512, JSON_THROW_ON_ERROR);

$f = fopen($vstup, 'r');
$prvy = (string) fgets($f);
rewind($f);
$sep = substr_count($prvy, ';') > substr_count($prvy, ',') ? ';' : ',';
$hlavicka = array_map(fn($h) => trim(ltrim($h, "\xEF\xBB\xBF")), (array) fgetcsv($f, null, $sep, '"', '\\'));
foreach (['zdroj', 'typ', 'obec', 'stav', 'cena', 'plocha'] as $s) {
    in_array($s, $hlavicka, true) || exit("Chýba stĺpec '$s'.\n");
}
$riadky = [];
while (($r = fgetcsv($f, null, $sep, '"', '\\')) !== false) {
    if ($r === [null]) { continue; }
    $riadky[] = array_combine($hlavicka, array_pad($r, count($hlavicka), ''));
}
$stavy = array_values(array_unique(array_filter(array_column($d['cennik'], 'stav'), fn($s) => $s !== 'všetky')));
$v = CennikBuilder::zostav($riadky, $d['lokality'], $d['zlavy'], $stavy);

echo count($riadky) . ' riadkov, ' . count($v['cennik']) . ' skupín, preskočených: ' . count($v['preskocene']) . ', varovaní: ' . count($v['varovania']) . "\n";
foreach (array_slice($v['preskocene'], 0, 20) as $p) { echo "preskočené: $p\n"; }
foreach (array_slice($v['varovania'], 0, 20) as $p) { echo "varovanie: $p\n"; }
if (!$zapis) { exit("Nič sa nezapísalo (použite --zapis).\n"); }
$d['cennik'] = $v['cennik'];
$d['vytvorene'] = date('Y-m-d');
file_put_contents($cesta, CennikBuilder::json($d));
echo "Zapísané do $cesta. Skontrolujte: php bin/kontrola-cennika.php\n";
