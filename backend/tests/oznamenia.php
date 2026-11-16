<?php
declare(strict_types=1);
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'Oznamovac'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Api, Config, Odhad, Oznamovac, Storage};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$st = new Storage(':memory:');
$api = new Api($odhad, new Config([]), $st);
$id = $api->handle('POST', '/api/odhad', ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66, 'dovod' => 'Predaj', 'adresa' => ['ulica' => 'Podhora 48', 'psc' => '034 01']])[1]['odhad_id'];

$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0905 123 456', 'meno' => 'Jana Nováková', 'odhad_id' => $id]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'bez@suhlasu.sk', 'odhad_id' => $id]);
$api->handle('POST', '/api/kontakt/pdf', ['email' => 'so@suhlasom.sk', 'suhlas' => true, 'odhad_id' => $id]);
$api->handle('POST', '/api/kontakt/mimo-regionu', ['email' => 'mimo@regionu.sk']);

$poslane = [];
$posli = function (string $komu, string $predmet, string $telo, ?string $odpoved) use (&$poslane): void { $poslane[] = compact('komu', 'predmet', 'telo', 'odpoved'); };
$webhook = [];
$hook = function (array $d) use (&$webhook): void { $webhook[] = $d; };

$check('bez nastavenia sa nič neoznamuje', ['oznamene' => 0, 'zlyhalo' => 0], (new Oznamovac($st, $posli, []))->spusti());
$check('bez nastavenia ostávajú leady v rade', 2, count($st->cakajuceOznamenia(5)));

$r = (new Oznamovac($st, $posli, ['jan@x.sk', 'info@x.sk'], $hook))->spusti();
$check('oznámené len telefonát a PDF so súhlasom', ['oznamene' => 2, 'zlyhalo' => 0], $r);
$check('e-mail každému príjemcovi', ['jan@x.sk', 'info@x.sk', 'jan@x.sk', 'info@x.sk'], array_column($poslane, 'komu'));
$check('predmet telefonátu', 'Nový lead: zavolať – Byt, 66 m², Ružomberok', $poslane[0]['predmet']);
$check('telo obsahuje telefón, adresu a odhad', [true, true, true, true], [
    str_contains($poslane[0]['telo'], 'Telefón: 0905 123 456'),
    str_contains($poslane[0]['telo'], 'Adresa: Podhora 48, 034 01 Ružomberok'),
    (bool) preg_match('/Odhad: 131.000 – 145.000 €/u', $poslane[0]['telo']),
    str_contains($poslane[0]['telo'], 'Dôvod: Predaj'),
]);
$check('odpoveď ide na e-mail klienta', [null, 'so@suhlasom.sk'], [$poslane[0]['odpoved'], $poslane[2]['odpoved']]);
$check('e-mail aj webhook obsahujú meno z kalkulačky', [true, 'Jana Nováková'], [str_contains($poslane[0]['telo'], 'Meno: Jana Nováková'), $webhook[0]['meno'] ?? null]);
$check('webhook dostal oba leady', [['telefonat', '0905 123 456', null], ['pdf_suhlas', null, 'so@suhlasom.sk']], array_map(fn($d) => [$d['ziadost'], $d['telefon'], $d['email']], $webhook));
$check('webhook má čísla ako čísla', [66.0, 131000], [$webhook[0]['vymera'], $webhook[0]['cena_od']]);
$check('druhý beh nič neposiela', ['oznamene' => 0, 'zlyhalo' => 0], (new Oznamovac($st, $posli, ['jan@x.sk']))->spusti());

$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0911 000 111', 'odhad_id' => $id]);
$zle = fn() => throw new RuntimeException('SMTP padlo');
$vysledky = [];
for ($i = 0; $i < 5; $i++) { $vysledky[] = (new Oznamovac($st, $zle, ['jan@x.sk'], null, 3))->spusti()['zlyhalo']; }
$check('zlyhanie sa opakuje najviac 3×', [1, 1, 1, 0, 0], $vysledky);

// Migrácia: v databáze spred zavedenia oznámení sa staré leady neoznamujú spätne.
$subor = sys_get_temp_dir() . '/nacenime-ozn-' . bin2hex(random_bytes(4)) . '.sqlite';
$pdo = new PDO('sqlite:' . $subor);
$pdo->exec("CREATE TABLE kontakty (id INTEGER PRIMARY KEY AUTOINCREMENT, vytvorene TEXT NOT NULL, druh TEXT NOT NULL, email TEXT, telefon TEXT, suhlas INTEGER NOT NULL DEFAULT 0, odhad_id INTEGER, odoslane INTEGER NOT NULL DEFAULT 0, pokusy INTEGER NOT NULL DEFAULT 0)");
$pdo->exec("INSERT INTO kontakty (vytvorene, druh, telefon) VALUES ('2026-11-14T10:00:00+00:00', 'makler', '0900 111 222')");
$pdo = null;
$stara = new Storage($subor);
$check('staré leady sa po migrácii neoznamujú', 0, count($stara->cakajuceOznamenia(5)));
$stara->ulozKontakt('makler', null, '0900 333 444', false, null);
$check('nové leady po migrácii áno', 1, count($stara->cakajuceOznamenia(5)));
$stara = null;
foreach ([$subor, "$subor-wal", "$subor-shm"] as $f) { if (is_file($f)) { unlink($f); } }

exit($fail > 0 ? 1 : 0);
