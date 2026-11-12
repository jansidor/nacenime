<?php
declare(strict_types=1);
require __DIR__ . '/../src/Odhad.php';

use Nacenime\Odhad;

$cennik = __DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json';
$o = Odhad::zo_suboru($cennik);
$fail = 0;
$check = function (string $nazov, mixed $ocakavane, mixed $skutocne) use (&$fail): void {
    if ($ocakavane === $skutocne) { echo "ok   $nazov\n"; return; }
    $fail++; echo "FAIL $nazov\n  ocakavane: " . json_encode($ocakavane, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skutocne, JSON_UNESCAPED_UNICODE) . "\n";
};
$pick = fn(array $r, array $k): array => array_map(fn($x) => $r[$x], $k);

$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66]);
$check('Byt Ružomberok 66', ['ok', 'Predaje', 'obec', 'Kompletná rekonštrukcia', 6, 131000, 145000], $pick($r, ['stav', 'zdroj', 'uroven', 'pouzity_stav', 'pocet', 'od', 'do']));

$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Martin', 'stav' => 'Pôvodný', 'vymera' => 60]);
$check('Byt Martin 60', ['Ponuky', 22, 111000, 132000], $pick($r, ['zdroj', 'pocet', 'od', 'do']));

$r = $o->odhad(['typ' => 'Apartmán', 'obec' => 'Demänovská Dolina', 'stav' => 'Novostavba', 'vymera' => 40]);
$check('Apartmán Demänovská', ['Ponuky', 'okres', 10, 137000, 169000], $pick($r, ['zdroj', 'uroven', 'pocet', 'od', 'do']));

$r = $o->odhad(['typ' => 'Pozemok', 'obec' => 'Liptovská Štiavnica', 'vymera' => 800]);
$check('Pozemok', ['Ponuky', 'okres', 'všetky', 17, 60000, 99000], $pick($r, ['zdroj', 'uroven', 'pouzity_stav', 'pocet', 'od', 'do']));
$check('Pozemok veta', true, str_ends_with($r['veta'], 'Bez rozlíšenia stavu. Rátané z celého okresu.'));

$r = $o->odhad(['typ' => 'Chata', 'obec' => 'Dolný Kubín', 'stav' => 'Pôvodný', 'vymera' => 50]);
$check('Chata málo dát', ['málo dát', 'Maklér vám povie cenu po obhliadke.'], [$r['stav'], $r['veta']]);
$check('Byt Námestovo málo dát', 'málo dát', $o->odhad(['typ' => 'Byt', 'obec' => 'Námestovo', 'stav' => 'Pôvodný', 'vymera' => 50])['stav']);
$check('mimo regiónu', 'mimo regiónu', $o->odhad(['typ' => 'Byt', 'obec' => 'Žilina', 'stav' => 'Pôvodný', 'vymera' => 50])['stav']);
$check('diakritika/veľkosť', 131000, $o->odhad(['typ' => 'Byt', 'obec' => 'ruzomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$check('RUŽOMBEROK veľké', 131000, $o->odhad(['typ' => 'Byt', 'obec' => ' RUŽOMBEROK ', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$nfd = "Ruz\u{030C}omberok"; // z + kombinujúci mäkčeň (rozložený zápis)
$check('rozložené znaky (NFD)', 131000, $o->odhad(['typ' => 'Byt', 'obec' => $nfd, 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$check('norm: ľ ť ň ô', 'lt no', Nacenime\Odhad::norm('Ľť Ňô'));
$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Martin', 'stav' => 'Pôvodný', 'vymera' => 60], ['Byt']);
$check('typ bez rozpätia', ['maklér', 'Cenu vám povie maklér'], [$r['stav'], $r['nadpis']]);

exit($fail > 0 ? 1 : 0);
