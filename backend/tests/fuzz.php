<?php
declare(strict_types=1);
// Fuzz: žiadny vstup nesmie spôsobiť výnimku, PHP varovanie ani 5xx. Seed sa dá nastaviť: php fuzz.php 123
foreach (['Config', 'Odhad', 'Validator', 'Storage', 'Adresy', 'Api', 'Leady'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Adresy, Api, Config, Odhad, Storage};

set_error_handler(function (int $no, string $msg, string $file, int $line): never { throw new ErrorException("$msg ($file:$line)", 0, $no); });
$seed = (int) ($argv[1] ?? 12345);
mt_srand($seed);

$odhad = Odhad::zo_suboru(__DIR__ . '/../../design_handoff_nacenime/data/nacenime-cennik.json');
$config = new Config(['NACENIME_GOOGLE_API_KEY' => 'k', 'NACENIME_RATE_LIMIT' => '1000000']);
// Falošný Google vracia aj nezmysly.
$http = function (string $m, string $u, array $h, ?string $b): array {
    $r = [[200, '{}'], [200, 'nie json'], [500, ''], [200, '{"suggestions":"x"}'], [200, '{"suggestions":[1,null,{"placePrediction":[]}]}'],
        [200, '{"location":{"latitude":"a","longitude":null},"addressComponents":"x"}'], [200, '{"location":{"latitude":1,"longitude":2},"addressComponents":[{"types":"x"},5]}']];
    return $r[mt_rand(0, count($r) - 1)];
};
$api = new Api($odhad, $config, new Storage(':memory:'), new Adresy($odhad, $config, $http));

$pooly = ['Byt', 'Dom', 'Pozemok', 'Hrad', '', ' ', 'Ružomberok', "Ruz\u{030C}omberok", 'Žilina', 'Pôvodný', 'Novostavba', 'všetky',
    "'; DROP TABLE odhady;--", '<script>alert(1)</script>', "\0", "a\0b", str_repeat('ž', 5000), '😀', "\u{202E}x", '../../etc/passwd',
    '=1+1', '66', '66,5', '1e999', '-5', '0', '9999999999999999999999', '１２３', 'NaN', 'INF', ' 66 ', "66\n"];
$cisla = [0, 1, -1, 10, 66, 2000, 2001, PHP_INT_MAX, PHP_INT_MIN, 0.1, -0.0, 1e308, INF, -INF, NAN, 1.5e-300];
$nahodna = function () use (&$nahodna, $pooly, $cisla): mixed {
    return match (mt_rand(0, 9)) {
        0 => null, 1 => true, 2 => false, 3 => $cisla[mt_rand(0, count($cisla) - 1)],
        4 => [], 5 => [$nahodna(), 'k' => $nahodna()], 6 => ['a' => ['b' => ['c' => 1]]],
        default => $pooly[mt_rand(0, count($pooly) - 1)],
    };
};
$polia = ['typ', 'obec', 'stav', 'vymera', 'dovod', 'balkon', 'predstava_ceny', 'adresa', 'odhad_id', 'email', 'telefon', 'suhlas', 'web', 'q', 'token', 'id'];
$cesty = [['POST', '/api/odhad'], ['POST', '/api/kontakt/pdf'], ['POST', '/api/kontakt/makler'], ['POST', '/api/kontakt/mimo-regionu'],
    ['GET', '/api/adresa'], ['GET', '/api/adresa/detail'], ['GET', '/api/obec'], ['GET', '/api/health'], ['GET', '/api/odhad'], ['DELETE', '/api/odhad'], ['GET', '//api//odhad'], ['POST', '']];

$n = 0; $kody = []; $chyby = [];
for ($i = 0; $i < 4000; $i++) {
    [$m, $c] = $cesty[mt_rand(0, count($cesty) - 1)];
    $telo = []; $q = [];
    foreach ($polia as $p) {
        if (mt_rand(0, 2) === 0) { continue; }
        $telo[$p] = $nahodna();
        if (mt_rand(0, 1)) { $q[$p] = $nahodna(); } // $_GET môže niesť aj polia
    }
    try {
        [$kod] = $api->handle($m, $c, $telo, $q, mt_rand(0, 3) === 0 ? '1.2.3.' . mt_rand(0, 3) : '');
        $kody[$kod] = ($kody[$kod] ?? 0) + 1;
        if ($kod >= 500) { $chyby[] = [$m, $c, 'HTTP ' . $kod]; }
    } catch (Throwable $e) {
        $chyby[] = [$m, $c, get_class($e) . ': ' . $e->getMessage(), json_encode([$telo, $q], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }
}
ksort($kody);
echo "seed $seed, 4000 požiadaviek, kódy: " . json_encode($kody) . ", chýb: " . count($chyby) . "\n";
foreach (array_slice(array_unique(array_map(fn($c) => $c[0] . ' ' . $c[1] . ' ' . $c[2], $chyby)), 0, 8) as $c) { echo "  $c\n"; }
exit($chyby ? 1 : 0);
