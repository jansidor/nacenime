<?php
declare(strict_types=1);
// Integračné testy cez skutočný HTTP server (php -S). Overujú public/index.php vrátane hlavičiek a limitov.

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};

$tmp = sys_get_temp_dir() . '/nacenime-http-' . bin2hex(random_bytes(4));
mkdir($tmp);
$port = random_int(20000, 40000);
$root = realpath(__DIR__ . '/..');
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', "$root/public", "$root/public/index.php"],
    [1 => ['file', "$tmp/out.log", 'w'], 2 => ['file', "$tmp/err.log", 'w']], $pipes, null,
    ['PATH' => (string) getenv('PATH'), 'NACENIME_DB_PATH' => "$tmp/test.sqlite", 'NACENIME_RATE_LIMIT' => '0', 'NACENIME_GOOGLE_API_KEY' => '']);
register_shutdown_function(function () use ($proc, $tmp) {
    proc_terminate($proc);
    array_map('unlink', glob("$tmp/*") ?: []);
    @rmdir($tmp);
});
for ($i = 0; $i < 50; $i++) { // počkaj na server
    if (@fsockopen('127.0.0.1', $port)) { break; }
    usleep(100000);
}

/** @return array{0: int, 1: array<string, mixed>|null, 2: string} kód, JSON telo, hlavičky */
$req = function (string $metoda, string $cesta, ?string $telo = null, string $ct = 'application/json') use ($port): array {
    $ch = curl_init("http://127.0.0.1:$port$cesta");
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metoda, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["Content-Type: $ct"]] + ($telo !== null ? [CURLOPT_POSTFIELDS => $telo] : []));
    $o = (string) curl_exec($ch);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $kod = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$kod, json_decode(substr($o, $hs), true), substr($o, 0, $hs)];
};
$v = json_encode(['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66]);

[$k, $j, $h] = $req('POST', '/api/odhad', $v);
$check('POST /api/odhad', [200, 131000, 145000], [$k, $j['od'] ?? null, $j['do'] ?? null]);
$check('hlavičky', [true, true, true, true], [
    str_contains($h, 'Content-Type: application/json; charset=utf-8'), str_contains($h, 'X-Content-Type-Options: nosniff'),
    str_contains($h, "Content-Security-Policy: default-src 'none'"), str_contains($h, 'Cache-Control: no-store')]);
$check('health', [200, 'ok'], [($x = $req('GET', '/api/health'))[0], $x[1]['stav'] ?? null]);
$check('POST bez JSON Content-Type = 415', 415, $req('POST', '/api/odhad', $v, 'application/x-www-form-urlencoded')[0]);
$check('zlý JSON = 400', [400, 'Neplatný JSON.'], [($x = $req('POST', '/api/odhad', '{zle'))[0], $x[1]['chyba'] ?? null]);
$check('JSON skalár = 400', 400, $req('POST', '/api/odhad', '"text"')[0]);
$check('príliš veľké telo = 413', 413, $req('POST', '/api/odhad', json_encode(['x' => str_repeat('a', 70000)]))[0]);
$check('neznáma cesta = 404', 404, $req('GET', '/api/neexistuje')[0]);
$check('GET na odhad = 405', 405, $req('GET', '/api/odhad')[0]);
$check('cesta s koncovým lomítkom', 200, $req('GET', '/api/health/')[0]);
$check('adresa bez kľúča = záloha', true, $req('GET', '/api/adresa?q=' . rawurlencode('Bystrická'))[1]['zaloha'] ?? false);
$check('obec cez query', 'Ružomberok', $req('GET', '/api/obec?obec=' . rawurlencode('ruzomberok'))[1]['obec'] ?? null);

$err = (string) @file_get_contents("$tmp/err.log");
$zle = array_values(array_filter(explode("\n", $err), fn($r) => preg_match('/Warning|Notice|Deprecated|Fatal/', $r) === 1));
$check('žiadne PHP warningy v logu', [], array_map(fn($r) => mb_substr($r, 0, 300), array_slice($zle, 0, 5)));
exit($fail > 0 ? 1 : 0);
