<?php
declare(strict_types=1);
// Súbežnosť: veľa paralelných zápisov proti serveru s viacerými workermi nesmie dať 5xx ("database is locked").
$tmp = sys_get_temp_dir() . '/nacenime-sub-' . bin2hex(random_bytes(4));
mkdir($tmp);
$port = random_int(20000, 40000);
$root = realpath(__DIR__ . '/..');
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', "$root/public", "$root/public/index.php"],
    [1 => ['file', "$tmp/out.log", 'w'], 2 => ['file', "$tmp/err.log", 'w']], $pipes, null,
    ['PATH' => (string) getenv('PATH'), 'PHP_CLI_SERVER_WORKERS' => '8', 'NACENIME_DB_PATH' => "$tmp/t.sqlite", 'NACENIME_RATE_LIMIT' => '0']);
register_shutdown_function(function () use ($proc, $tmp) {
    proc_terminate($proc);
    array_map('unlink', glob("$tmp/*") ?: []);
    @rmdir($tmp);
});
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) { usleep(100000); }

$n = 200;
$telo = json_encode(['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66]);
$mh = curl_multi_init();
$uchy = [];
for ($i = 0; $i < $n; $i++) {
    $ch = curl_init("http://127.0.0.1:$port/api/odhad");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $telo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    curl_multi_add_handle($mh, $ch);
    $uchy[] = $ch;
}
do { curl_multi_exec($mh, $bezi); curl_multi_select($mh, 0.1); } while ($bezi > 0);
$kody = [];
foreach ($uchy as $ch) { $k = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $kody[$k] = ($kody[$k] ?? 0) + 1; }
ksort($kody);
$ulozene = (int) (new PDO("sqlite:$tmp/t.sqlite"))->query('SELECT COUNT(*) FROM odhady')->fetchColumn();
echo "kódy: " . json_encode($kody) . ", uložených odhadov: $ulozene z $n\n";
$locked = substr_count((string) @file_get_contents("$tmp/err.log"), 'locked');
echo "záznamov 'locked' v logu: $locked\n";
$ok = $kody === [200 => $n] && $ulozene === $n && $locked === 0;
echo $ok ? "ok   súbežné zápisy bez chýb\n" : "FAIL súbežné zápisy\n";

// Súbežná inicializácia: viac procesov naraz vytvára novú databázu (WAL + schéma) nesmie skončiť "database is locked".
$kolo = 30;
$procesov = 12;
$chybyInit = 0;
$kod = 'require $argv[1]; while (microtime(true) < (float) $argv[3]) {} '
    . 'try { (new Nacenime\Storage($argv[2]))->zvysLimit("x"); } catch (Throwable $e) { fwrite(STDERR, "ERR " . $e->getMessage()); exit(1); }';
for ($k = 0; $k < $kolo; $k++) {
    $db = "$tmp/init-$k.sqlite";
    $start = microtime(true) + 0.4;
    $deti = [];
    for ($p = 0; $p < $procesov; $p++) {
        $deti[] = proc_open(['php', '-r', $kod, '--', "$root/src/Storage.php", $db, (string) $start],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', "$tmp/init-err-$k-$p.log", 'w']], $pipes);
    }
    foreach ($deti as $d) {
        if (proc_close($d) !== 0) {
            $chybyInit++;
        }
    }
}
array_map('unlink', glob("$tmp/init-*") ?: []);
echo "inicializácia: $kolo kôl po $procesov procesov, chýb: $chybyInit\n";
$okInit = $chybyInit === 0;
echo $okInit ? "ok   súbežná inicializácia databázy bez chýb\n" : "FAIL súbežná inicializácia databázy\n";
exit($ok && $okInit ? 0 : 1);
