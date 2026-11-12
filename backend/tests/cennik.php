<?php
declare(strict_types=1);
foreach (['Odhad', 'Validator', 'CennikBuilder'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{CennikBuilder, Odhad};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$cesta = __DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json';
$raw = (string) file_get_contents($cesta);
$d = json_decode($raw, true);
$check('serializácia je bajt po bajte rovnaká ako cenník', $raw, CennikBuilder::json($d));

$check('kvantil n=4', [1750.0, 2500.0, 3250.0], array_map(fn($p) => CennikBuilder::kvantil([1000, 2000, 3000, 4000], $p), [0.25, 0.5, 0.75]));
$check('kvantil n=3', [1500.0, 2000.0, 2500.0], array_map(fn($p) => CennikBuilder::kvantil([1000, 2000, 3000], $p), [0.25, 0.5, 0.75]));
$check('kvantil n=1', 777.0, CennikBuilder::kvantil([777], 0.25));

$lok = ['Ružomberok' => 'Ružomberok', 'Likavka' => 'Ružomberok', 'Martin' => 'Martin'];
$zlavy = ['Byt' => [1, 2], 'Pozemok' => [0, 0]];
$stavy = ['Pôvodný', 'Novostavba'];
$r = fn(string $z, string $t, string $o, string $s, int $cena, int $plocha) => ['zdroj' => $z, 'typ' => $t, 'obec' => $o, 'stav' => $s, 'cena' => (string) $cena, 'plocha' => (string) $plocha];
$riadky = [
    $r('Predaje', 'Byt', 'Ružomberok', 'Pôvodný', 100000, 50), // 2000
    $r('Predaje', 'Byt', 'ruzomberok', 'Pôvodný', 150000, 50), // 3000
    $r('Predaje', 'Byt', 'Likavka', 'Pôvodný', 200000, 50),    // 4000
    $r('Predaje', 'Byt', 'Likavka', 'Novostavba', 50000, 50),  // 1000
    $r('Predaje', 'Byt', 'Žilina', 'Pôvodný', 1, 1),           // mimo regiónu
    $r('Predaje', 'Byt', 'Martin', 'Hrad', 100000, 50),        // neznámy stav
    $r('Predaje', 'Byt', 'Martin', 'Pôvodný', 'abc' === 'x' ? 1 : 100000, 0), // plocha 0
];
$v = CennikBuilder::zostav($riadky, $lok, $zlavy, $stavy);
$kluce = array_column($v['cennik'], null, 'kluc');
$check('okres so stavom: Ružomberok Pôvodný (3 záznamy)', [3, 2500, 3000, 3500], [$kluce['Predaje|Byt|okres Ružomberok|Pôvodný']['pocet'], $kluce['Predaje|Byt|okres Ružomberok|Pôvodný']['q1'], $kluce['Predaje|Byt|okres Ružomberok|Pôvodný']['median'], $kluce['Predaje|Byt|okres Ružomberok|Pôvodný']['q3']]);
$check('okres „všetky“ má 4 záznamy', [4, 'okres'], [$kluce['Predaje|Byt|okres Ružomberok|všetky']['pocet'], $kluce['Predaje|Byt|okres Ružomberok|všetky']['uroven']]);
$check('obec Ružomberok má len 2 (pod minimom = bez skupiny)', false, isset($kluce['Predaje|Byt|Ružomberok|Pôvodný']));
$check('skupina Novostavba má 1 záznam, nevzniká', false, isset($kluce['Predaje|Byt|okres Ružomberok|Novostavba']));
$check('preskočené riadky', 3, count($v['preskocene']));
$check('polia záznamu ako v cenníku', array_keys($d['cennik'][0]), array_keys($kluce['Predaje|Byt|okres Ružomberok|Pôvodný']));

// pozemok: stav sa ignoruje, všetko ide do „všetky“
$poz = array_map(fn($c) => $r('Ponuky', 'Pozemok', 'Martin', 'Čokoľvek', $c * 100, 100), [50, 60, 70]);
$vp = CennikBuilder::zostav($poz, $lok, $zlavy, $stavy);
$check('pozemok: len stav „všetky“', ['Ponuky|Pozemok|Martin|všetky', 'Ponuky|Pozemok|okres Martin|všetky'], array_column($vp['cennik'], 'kluc'));

// podozrivá cena sa zahrnie s varovaním
$vv = CennikBuilder::zostav(array_map(fn($c) => $r('Predaje', 'Byt', 'Martin', 'Pôvodný', $c, 100), [1000, 2000, 3000]), $lok, $zlavy, $stavy);
$check('podozrivé €/m² = varovanie, záznam zahrnutý', [3, 3], [count($vv['varovania']), $vv['cennik'][0]['pocet']]);

// výstup je použiteľný výpočtom odhadu
$novy = $d; $novy['cennik'] = $v['cennik'];
$o = new Odhad($novy);
$res = $o->odhad(['typ' => 'Byt', 'obec' => 'Likavka', 'stav' => 'Pôvodný', 'vymera' => 60]);
// Prah predajov je 4: skupina so stavom má len 3 záznamy, použije sa „všetky“ (1000, 2000, 3000, 4000 €/m²).
$check('odhad z novo zostaveného cenníka', ['ok', 'okres', 'všetky', 105000, 195000], [$res['stav'], $res['uroven'], $res['pouzity_stav'], $res['od'], $res['do']]);

exit($fail > 0 ? 1 : 0);
