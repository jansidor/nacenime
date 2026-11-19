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
$check('Byt Námestovo: približne podľa susedného okresu', ['ok', 'susedný okres'], [($x = $o->odhad(['typ' => 'Byt', 'obec' => 'Námestovo', 'stav' => 'Pôvodný', 'vymera' => 50]))['stav'], $x['uroven']]);
$check('mimo regiónu', 'mimo regiónu', $o->odhad(['typ' => 'Byt', 'obec' => 'Žilina', 'stav' => 'Pôvodný', 'vymera' => 50])['stav']);
$check('diakritika/veľkosť', 131000, $o->odhad(['typ' => 'Byt', 'obec' => 'ruzomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$check('RUŽOMBEROK veľké', 131000, $o->odhad(['typ' => 'Byt', 'obec' => ' RUŽOMBEROK ', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$nfd = "Ruz\u{030C}omberok"; // z + kombinujúci mäkčeň (rozložený zápis)
$check('rozložené znaky (NFD)', 131000, $o->odhad(['typ' => 'Byt', 'obec' => $nfd, 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66])['od']);
$check('norm: ľ ť ň ô', 'lt no', Nacenime\Odhad::norm('Ľť Ňô'));
foreach (['Tvrdošín', 'Turčianske Teplice', 'Námestovo', 'Zuberec', 'Čremošné', 'Oravská Lesná'] as $obec) {
    $check("obec $obec je v regióne", true, $o->odhad(['typ' => 'Byt', 'obec' => $obec, 'stav' => 'Pôvodný', 'vymera' => 50])['stav'] !== 'mimo regiónu');
}
$check('Trstená (Tvrdošín) a Trstené (Liptov) sú rozlíšené', ['Tvrdošín', 'Liptovský Mikuláš'], [$o->najdiObec('Trstená')['okres'], $o->najdiObec('Trstené')['okres']]);
$check('všetkých 7 okresov má obce', 7, count(array_unique(array_column((new ReflectionProperty($o, 'lokality'))->getValue($o), 'okres'))));
$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Martin', 'stav' => 'Pôvodný', 'vymera' => 60], ['Byt']);
$check('typ bez rozpätia', ['maklér', 'Cenu vám povie maklér'], [$r['stav'], $r['nadpis']]);

// okresy bez vlastných dát: približne podľa susedného okresu
$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Trstená', 'stav' => 'Pôvodný', 'vymera' => 60]);
$check('Tvrdošín: približne podľa Dolného Kubína', ['ok', 'susedný okres', true, true], [$r['stav'], $r['uroven'], str_contains($r['veta'], 'susednom okrese Dolný Kubín'), str_contains($r['veta'], 'Pre okres Tvrdošín zatiaľ nemáme vlastné údaje.')]);
$dk = $o->odhad(['typ' => 'Byt', 'obec' => 'Dolný Kubín', 'stav' => 'Pôvodný', 'vymera' => 60]);
$check('rovnaké rozpätie ako okres Dolný Kubín (ak tam nie je skupina obce)', true, $r['od'] > 0 && $r['do'] >= $r['od']);
$r = $o->odhad(['typ' => 'Byt', 'obec' => 'Turčianske Teplice', 'stav' => 'Pôvodný', 'vymera' => 60]);
$check('Turčianske Teplice: byt sa neodhaduje podľa Martina (mestá sa líšia)', 'málo dát', $r['stav']);
$r = $o->odhad(['typ' => 'Pozemok', 'obec' => 'Turčianske Teplice', 'stav' => null, 'vymera' => 800]);
$check('Turčianske Teplice: pozemok približne podľa Martina', ['ok', true], [$r['stav'], str_contains($r['veta'], 'okrese Martin')]);
// typy, pre ktoré sa susedný okres NEsmie použiť (rekreačné a vzorkovo tenké segmenty): vždy málo dát
foreach ([['Dom', 'Trstená'], ['Dom', 'Turčianske Teplice'], ['Chata', 'Námestovo'], ['Chata', 'Trstená'], ['Apartmán', 'Zuberec'], ['Apartmán', 'Turčianske Teplice'], ['Chata', 'Turčianske Teplice']] as [$t, $ob]) {
    $x = $o->odhad(['typ' => $t, 'obec' => $ob, 'stav' => 'Pôvodný', 'vymera' => 70]);
    $check("$t, $ob: bez rozpätia (málo dát)", ['málo dát', null], [$x['stav'], $x['od']]);
}
// typy, pre ktoré je susedný okres prijateľná náhrada
foreach ([['Byt', 'Námestovo', 'Dolný Kubín'], ['Byt', 'Trstená', 'Dolný Kubín'], ['Dom', 'Námestovo', 'Dolný Kubín'], ['Pozemok', 'Námestovo', 'Dolný Kubín'], ['Pozemok', 'Trstená', 'Dolný Kubín']] as [$t, $ob, $sus]) {
    $x = $o->odhad(['typ' => $t, 'obec' => $ob, 'stav' => $t === 'Pozemok' ? null : 'Pôvodný', 'vymera' => $t === 'Pozemok' ? 800 : 70]);
    $check("$t, $ob: približne podľa $sus", ['ok', true], [$x['stav'], str_contains($x['veta'] ?? '', "okrese $sus")]);
}
$r = $o->odhad(['typ' => 'Apartmán', 'obec' => 'Námestovo', 'stav' => 'Novostavba', 'vymera' => 50]);
$check('ani susedný okres nemá dáta: málo dát', 'málo dát', $r['stav']);
$check('okres s vlastnými dátami sa nemení', ['ok', 'obec'], [($x = $o->odhad(['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Pôvodný', 'vymera' => 60]))['stav'], $x['uroven']]);
$bez = json_decode(file_get_contents($cennik), true); unset($bez['nahradne_okresy']);
$check('bez nastavenia nahradne_okresy: málo dát (pôvodné správanie)', 'málo dát', (new Odhad($bez))->odhad(['typ' => 'Byt', 'obec' => 'Trstená', 'stav' => 'Pôvodný', 'vymera' => 60])['stav']);
$vlastne = json_decode(file_get_contents($cennik), true);
$vlastne['cennik'][] = ['kluc' => 'Predaje|Byt|okres Tvrdošín|všetky', 'zdroj' => 'Predaje', 'typ' => 'Byt', 'uroven' => 'okres', 'lokalita' => 'okres Tvrdošín', 'stav' => 'všetky', 'pocet' => 10, 'q1' => 1000, 'median' => 1100, 'q3' => 1200];
$check('vlastné dáta okresu majú prednosť pred susedným', ['okres', 60000, 72000], [($x = (new Odhad($vlastne))->odhad(['typ' => 'Byt', 'obec' => 'Trstená', 'stav' => 'Pôvodný', 'vymera' => 60]))['uroven'], $x['od'], $x['do']]);

exit($fail > 0 ? 1 : 0);
