<?php
declare(strict_types=1);
// Zostaví cenník zo surových predajov a ponúk.
// Použitie: php bin/zostav-cennik.php vstup.csv [--zapis] [--doplnit | --nahradit-vsetko]
//   vstup.csv: hlavička zdroj,typ,obec,stav,cena,plocha (oddeľovač čiarka alebo bodkočiarka)
//   zdroj = Predaje | Ponuky; typ = Byt|Apartmán|Dom|Chata|Pozemok; stav (pri pozemku prázdny); plocha v m² (pozemok: plocha pozemku)
//   Bez --zapis sa len vypíše súhrn (nič sa nemení).
//   --zapis --doplnit: nahradí len okresy, ktoré sú vo vstupe (napr. nové dáta pre Tvrdošín), ostatné ostanú.
//   --zapis --nahradit-vsetko: prepíše celý cenník (vstup musí obsahovať všetky okresy).
//   Ak vstup nepokrýva všetky okresy starého cenníka, --zapis bez --doplnit odmietne zapisovať (ochrana pred stratou dát).
require __DIR__ . '/../src/Odhad.php';
require __DIR__ . '/../src/Validator.php';
require __DIR__ . '/../src/CennikBuilder.php';

use Nacenime\CennikBuilder;

$vstup = $argv[1] ?? '';
$zapis = in_array('--zapis', $argv, true);
$doplnit = in_array('--doplnit', $argv, true);
$vsetko = in_array('--nahradit-vsetko', $argv, true);
is_file($vstup) || exit("Použitie: php bin/zostav-cennik.php vstup.csv [--zapis] [--doplnit | --nahradit-vsetko]\n");
!($doplnit && $vsetko) || exit("Použite buď --doplnit, alebo --nahradit-vsetko.\n");
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
$vstupOkresy = CennikBuilder::okresy($v['cennik'], $d['lokality']);
$stareOkresy = CennikBuilder::okresy($d['cennik'], $d['lokality']);
$chybaju = array_values(array_diff($stareOkresy, $vstupOkresy));
echo 'okresy vo vstupe: ' . ($vstupOkresy ? implode(', ', $vstupOkresy) : 'žiadne') . "\n";
if ($chybaju && !$doplnit) {
    echo 'POZOR: vstup neobsahuje okresy ' . implode(', ', $chybaju) . ". Prepísaním celého cenníka by sa ich ceny stratili.\n";
}
if (!$zapis) { exit("Nič sa nezapísalo (použite --zapis s --doplnit alebo --nahradit-vsetko).\n"); }
$stop = function (string $sprava): never { fwrite(STDERR, $sprava . "\n"); exit(1); };
!($chybaju && !$doplnit) || $stop('Nič sa nezapísalo. Na doplnenie len týchto okresov použite --doplnit.');
!(!$doplnit && !$vsetko) || $stop('Nič sa nezapísalo. Zvoľte --doplnit (nahradí len okresy zo vstupu) alebo --nahradit-vsetko.');
$vstupOkresy || $stop('Vstup nevytvoril žiadnu skupinu, nič sa nezapísalo.');
$d['cennik'] = $doplnit ? CennikBuilder::doplnOkresy($d['cennik'], $v['cennik'], $d['lokality']) : $v['cennik'];
$d['vytvorene'] = date('Y-m-d');
file_put_contents($cesta, CennikBuilder::json($d));
echo "Zapísané do $cesta. Skontrolujte: php bin/kontrola-cennika.php\n";
