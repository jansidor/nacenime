<?php
declare(strict_types=1);
// Kontrola konzistencie cenníka. Použitie: php bin/kontrola-cennika.php [cesta]. Exit 1 pri chybe.

$cesta = $argv[1] ?? __DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json';
$d = json_decode((string) file_get_contents($cesta), true, 512, JSON_THROW_ON_ERROR);
$chyby = [];
$varovania = [];

foreach (['prahy', 'zlavy', 'lokality', 'cennik'] as $k) {
    if (!isset($d[$k])) { $chyby[] = "chýba kľúč '$k'"; }
}
if ($chyby) { fwrite(STDERR, implode("\n", $chyby) . "\n"); exit(1); }

$typy = array_keys($d['zlavy']);
$okresy = array_values(array_unique($d['lokality']));
$kluce = [];
foreach ($d['cennik'] as $g) {
    $id = $g['kluc'] ?? '?';
    if (isset($kluce[$id])) { $chyby[] = "$id: duplicitný kľúč"; }
    $kluce[$id] = true;
    $ocakavany = implode('|', [$g['zdroj'], $g['typ'], $g['lokalita'], $g['stav']]);
    if ($id !== $ocakavany) { $chyby[] = "$id: kľúč nesedí s poľami ($ocakavany)"; }
    if (!in_array($g['typ'], $typy, true)) { $chyby[] = "$id: neznámy typ"; }
    if (!in_array($g['zdroj'], ['Predaje', 'Ponuky'], true)) { $chyby[] = "$id: neznámy zdroj"; }
    if (!($g['pocet'] > 0)) { $chyby[] = "$id: počet musí byť kladný"; }
    if (!($g['q1'] > 0 && $g['q1'] <= $g['median'] && $g['median'] <= $g['q3'])) { $chyby[] = "$id: neplatí 0 < q1 ≤ median ≤ q3"; }
    if ($g['uroven'] === 'obec' && !isset($d['lokality'][$g['lokalita']])) { $chyby[] = "$id: obec nie je v lokalitách"; }
    if ($g['uroven'] === 'okres' && !in_array(preg_replace('/^okres /', '', $g['lokalita']), $okresy, true)) { $chyby[] = "$id: neznámy okres"; }
    if ($g['q3'] > 20000) { $varovania[] = "$id: q3 = {$g['q3']} €/m² je podozrivo vysoké"; }
}
foreach ($typy as $t) {
    [$min, $max] = $d['zlavy'][$t];
    if ($min > $max) { $chyby[] = "zľavy $t: min > max"; }
}
foreach ($okresy as $o) { // každý okres by mal mať aspoň jednu skupinu na zálohu „všetky“
    $ma = false;
    foreach ($d['cennik'] as $g) { $ma = $ma || ($g['uroven'] === 'okres' && $g['lokalita'] === "okres $o" && $g['stav'] === 'všetky'); }
    if (!$ma) { $varovania[] = "okres $o: žiadna skupina na úrovni okresu so stavom „všetky“"; }
}
foreach ($d['lokality'] as $obec => $okres) {
    if (!in_array($okres, ['Ružomberok', 'Liptovský Mikuláš', 'Martin', 'Turčianske Teplice', 'Dolný Kubín', 'Námestovo', 'Tvrdošín'], true)) {
        $chyby[] = "obec $obec: okres '$okres' nepatrí medzi 7 podporovaných";
    }
}
foreach ($varovania as $v) { echo "varovanie: $v\n"; }
foreach ($chyby as $c) { fwrite(STDERR, "CHYBA: $c\n"); }
echo count($d['cennik']) . ' skupín, ' . count($d['lokality']) . ' obcí, chýb: ' . count($chyby) . ', varovaní: ' . count($varovania) . "\n";
exit($chyby ? 1 : 0);
