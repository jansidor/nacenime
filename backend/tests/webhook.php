<?php
declare(strict_types=1);
// Podpis odchádzajúceho webhooku (HMAC-SHA256): jednotkové testy a doručenie na skutočný HTTP prijímač (php -S).
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'WebhookPodpis', 'Oznamovac', 'Cesty', 'Diagnostika'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Api, Config, Diagnostika, Odhad, Oznamovac, Storage, WebhookPodpis};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};

$tajomstvo = str_repeat('a1b2c3d4', 8); // 64 znakov
$telo = '{"zdroj":"nacenime.sk","id":7,"meno":"Jana Nováková"}';
$t = 1_800_000_000;

// ── jednotkové testy podpisu ──
$sig = WebhookPodpis::podpis($telo, $tajomstvo, $t);
$check('podpis je hex SHA-256 (64 znakov)', 1, preg_match('/^[0-9a-f]{64}$/', $sig));
$check('podpis zodpovedá HMAC z "čas.telo"', hash_hmac('sha256', "$t.$telo", $tajomstvo), $sig);
$check('rovnaký vstup = rovnaký podpis', $sig, WebhookPodpis::podpis($telo, $tajomstvo, $t));
$check('iné telo = iný podpis', false, $sig === WebhookPodpis::podpis($telo . ' ', $tajomstvo, $t));
$check('iný čas = iný podpis', false, $sig === WebhookPodpis::podpis($telo, $tajomstvo, $t + 1));
$check('iné tajomstvo = iný podpis', false, $sig === WebhookPodpis::podpis($telo, $tajomstvo . 'x', $t));
$check('ID udalosti je stabilné pre lead', 'nacenime-lead-7', WebhookPodpis::idUdalosti(7));

$h = WebhookPodpis::hlavicky($telo, $tajomstvo, 'nacenime-lead-7', $t);
$check('hlavičky s tajomstvom: JSON, ID udalosti, podpis', ['Content-Type: application/json', 'X-Nacenime-Event-Id: nacenime-lead-7', "X-Nacenime-Signature: t=$t,v1=$sig"], $h);
$h0 = WebhookPodpis::hlavicky($telo, '', 'nacenime-lead-7', $t);
$check('bez tajomstva sa podpis neposiela (správanie ako doteraz)', ['Content-Type: application/json', 'X-Nacenime-Event-Id: nacenime-lead-7'], $h0);

$hlavicka = "t=$t,v1=$sig";
$check('overenie: platný podpis', null, WebhookPodpis::over($hlavicka, $telo, $tajomstvo, $t));
$check('overenie: tolerancia 300 s (hranica ešte platí)', null, WebhookPodpis::over($hlavicka, $telo, $tajomstvo, $t + 300));
$check('overenie: o sekundu staršie sa zamietne', 'časová pečiatka mimo tolerancie', WebhookPodpis::over($hlavicka, $telo, $tajomstvo, $t + 301));
$check('overenie: pečiatka z budúcnosti sa zamietne', 'časová pečiatka mimo tolerancie', WebhookPodpis::over($hlavicka, $telo, $tajomstvo, $t - 301));
$check('overenie: zmenené telo', 'podpis nesedí', WebhookPodpis::over($hlavicka, $telo . 'x', $tajomstvo, $t));
$check('overenie: iné tajomstvo', 'podpis nesedí', WebhookPodpis::over($hlavicka, $telo, 'zle' . $tajomstvo, $t));
$check('overenie: podvrhnutá pečiatka (nový čas, starý podpis)', 'podpis nesedí', WebhookPodpis::over("t=" . ($t + 10) . ",v1=$sig", $telo, $tajomstvo, $t + 10));
$check('overenie: prázdna hlavička', 'neplatný formát podpisu', WebhookPodpis::over('', $telo, $tajomstvo, $t));
$check('overenie: zlý formát', 'neplatný formát podpisu', WebhookPodpis::over("t=$t,v1=abc", $telo, $tajomstvo, $t));
$check('overenie: veľké písmená v podpise sa nepripúšťajú', 'neplatný formát podpisu', WebhookPodpis::over("t=$t,v1=" . strtoupper($sig), $telo, $tajomstvo, $t));
$check('overenie: bez tajomstva na strane prijímača', 'tajomstvo nie je nastavené', WebhookPodpis::over($hlavicka, $telo, '', $t));

// ── diagnostika konfigurácie ──
$root = realpath(__DIR__ . '/..');
$tmp = sys_get_temp_dir() . '/nacenime-wh-' . bin2hex(random_bytes(4));
mkdir($tmp);
mkdir("$tmp/var");
copy(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json', "$tmp/cennik.json");
$zakl = ['NACENIME_CENNIK_PATH' => "$tmp/cennik.json", 'NACENIME_DB_PATH' => "$tmp/var/n.sqlite"];
$rozsirenia = ['mbstring', 'pdo_sqlite', 'curl', 'dom', 'gd', 'intl'];
$hladaj = fn(array $v, string $text, string $uroven) => count(array_filter($v, fn($r) => $r[0] === $uroven && str_contains($r[1], $text)));
$diag = fn(array $extra) => Diagnostika::skontroluj(new Config($extra + $zakl), $root, $rozsirenia, '8.3.0');
$check('diagnostika: webhook bez tajomstva = varovanie', 1, $hladaj($diag(['NACENIME_LEAD_WEBHOOK' => 'https://x.sk/h']), 'nie je podpisovaný', 'VAROVANIE'));
$check('diagnostika: krátke tajomstvo = chyba', 1, $hladaj($diag(['NACENIME_LEAD_WEBHOOK' => 'https://x.sk/h', 'NACENIME_LEAD_WEBHOOK_SECRET' => 'kratke']), 'aspoň 32 znakov', 'CHYBA'));
$check('diagnostika: platné tajomstvo = ok', 1, $hladaj($diag(['NACENIME_LEAD_WEBHOOK' => 'https://x.sk/h', 'NACENIME_LEAD_WEBHOOK_SECRET' => $tajomstvo]), 'webhook je podpisovaný', 'OK'));
$check('diagnostika: tajomstvo bez webhooku = varovanie', 1, $hladaj($diag(['NACENIME_LEAD_EMAIL' => 'a@b.sk', 'NACENIME_LEAD_WEBHOOK_SECRET' => $tajomstvo]), 'podpis sa nikam neposiela', 'VAROVANIE'));
$check('diagnostika: bez webhooku ani tajomstva sa nehlási nič', 0, $hladaj($diag(['NACENIME_LEAD_EMAIL' => 'a@b.sk']), 'podpis', 'VAROVANIE'));

// ── doručenie na skutočný HTTP prijímač ──
$prijimac = <<<'PHP'
<?php
// Skúšobný prijímač: overí podpis rovnako ako rozia.sk a zapíše ID udalosti.
require getenv('SRC') . '/WebhookPodpis.php';
$telo = file_get_contents('php://input');
$id = $_SERVER['HTTP_X_NACENIME_EVENT_ID'] ?? '';
$podpis = $_SERVER['HTTP_X_NACENIME_SIGNATURE'] ?? '';
$cesta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents(getenv('LOG'), json_encode(['cesta' => $cesta, 'id' => $id, 'podpis' => $podpis !== ''], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
if ($cesta === '/zlyhaj') { http_response_code(500); exit; }
if ($cesta === '/otvoreny') { http_response_code(204); exit; }
$dovod = Nacenime\WebhookPodpis::over($podpis, $telo, getenv('SECRET'));
if ($dovod !== null) { http_response_code(401); echo $dovod; exit; }
http_response_code(204);
PHP;
file_put_contents("$tmp/prijimac.php", $prijimac);
$port = random_int(20000, 40000);
$proc = proc_open(['php', '-S', "127.0.0.1:$port", "$tmp/prijimac.php"],
    [1 => ['file', "$tmp/out.log", 'w'], 2 => ['file', "$tmp/err.log", 'w']], $pipes, null,
    ['PATH' => (string) getenv('PATH'), 'SRC' => "$root/src", 'LOG' => "$tmp/prijate.log", 'SECRET' => $tajomstvo]);
register_shutdown_function(function () use ($proc, $tmp) {
    proc_terminate($proc);
    array_map('unlink', glob("$tmp/var/*") ?: []);
    @rmdir("$tmp/var");
    array_map('unlink', glob("$tmp/*") ?: []);
    @rmdir($tmp);
});
for ($i = 0; $i < 50; $i++) {
    if (@fsockopen('127.0.0.1', $port)) { break; }
    usleep(100000);
}
$url = "http://127.0.0.1:$port";
$prijate = fn(): array => array_map(fn($r) => json_decode($r, true), file($tmp . '/prijate.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
$data = ['zdroj' => 'nacenime.sk', 'id' => 7, 'meno' => 'Jana Nováková', 'telefon' => '0905 123 456'];
$skus = function (callable $webhook) use ($data): ?string {
    try { $webhook($data); return null; } catch (\RuntimeException $e) { return $e->getMessage(); }
};

$check('prijímač: podpísaný webhook prejde', null, $skus(Oznamovac::webhook("$url/podpisany", $tajomstvo)));
$check('prijímač: s iným tajomstvom sa zamietne (401)', 'Webhook vrátil 401 ', $skus(Oznamovac::webhook("$url/podpisany", 'iné-tajomstvo-iné-tajomstvo-iné-tajomstvo'))); // curl_error je prázdne
$check('prijímač: bez tajomstva podpis chýba, prijímač vyžadujúci podpis ho zamietne', 'Webhook vrátil 401 ', $skus(Oznamovac::webhook("$url/podpisany")));
$check('prijímač: bez tajomstva ide webhook ďalej na otvorený prijímač (spätná kompatibilita)', null, $skus(Oznamovac::webhook("$url/otvoreny")));
$p = $prijate();
$check('prijímač dostal ID udalosti vo všetkých pokusoch', ['nacenime-lead-7', 'nacenime-lead-7', 'nacenime-lead-7', 'nacenime-lead-7'], array_column($p, 'id'));
$check('podpis sa posiela len s tajomstvom', [true, true, false, false], array_column($p, 'podpis'));

// Opakované doručenie: prvý pokus zlyhá (500), druhý prejde; ID udalosti ostáva rovnaké, lead sa oznámi raz.
$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$st = new Storage(':memory:');
$api = new Api($odhad, new Config([]), $st);
$odhadId = $api->handle('POST', '/api/odhad', ['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66, 'dovod' => 'Predaj', 'adresa' => ['ulica' => 'Podhora 48', 'psc' => '034 01']])[1]['odhad_id'];
$api->handle('POST', '/api/kontakt/makler', ['telefon' => '0905 123 456', 'meno' => 'Jana Nováková', 'odhad_id' => $odhadId]);
@unlink("$tmp/prijate.log");
$posli = fn() => null;
$r1 = (new Oznamovac($st, $posli, [], Oznamovac::webhook("$url/zlyhaj", $tajomstvo)))->spusti();
$r2 = (new Oznamovac($st, $posli, [], Oznamovac::webhook("$url/podpisany", $tajomstvo)))->spusti();
$r3 = (new Oznamovac($st, $posli, [], Oznamovac::webhook("$url/podpisany", $tajomstvo)))->spusti();
$check('opakovanie: 1. pokus zlyhá, 2. prejde, 3. už nič', [['oznamene' => 0, 'zlyhalo' => 1], ['oznamene' => 1, 'zlyhalo' => 0], ['oznamene' => 0, 'zlyhalo' => 0]], [$r1, $r2, $r3]);
$p = $prijate();
$check('opakovanie: oba pokusy nesú rovnaké ID udalosti', ['nacenime-lead-1', 'nacenime-lead-1'], array_column($p, 'id'));
$check('opakovanie: druhý pokus prešiel overením podpisu', ['/zlyhaj', '/podpisany'], array_column($p, 'cesta'));

exit($fail > 0 ? 1 : 0);
