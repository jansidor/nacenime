<?php
declare(strict_types=1);
// Simulácia nasadenia z Gitu: …/web/release_<čas>/ (kód) a …/web/shared/ (.env a databáza).
// Overuje, že .env a databáza sa berú zo `shared`, že bez `shared` API odmietne bežať a že sa nič nezapíše do releasu.
foreach (['Config', 'Cesty'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Cesty, Config};

$fail = 0;
$check = function (string $n, mixed $ocak, mixed $skut) use (&$fail): void {
    if ($ocak === $skut) { echo "ok   $n\n"; return; }
    $fail++; echo "FAIL $n\n  ocakavane: " . json_encode($ocak, JSON_UNESCAPED_UNICODE) . "\n  skutocne:  " . json_encode($skut, JSON_UNESCAPED_UNICODE) . "\n";
};
$koren = realpath(__DIR__ . '/../..');
$web = sys_get_temp_dir() . '/nacenime-nasadenie-' . bin2hex(random_bytes(4));
$release = "$web/release_20261113120000";
mkdir($web);
exec('bash ' . escapeshellarg("$koren/deploy/zostav-deploy-strom.sh") . ' ' . escapeshellarg($release) . ' --bez-vendor 2>&1', $vystup, $kod);
$check('strom sa zostaví', 0, $kod);
$strom = static fn(string $f): bool => file_exists("$release/$f");
$check('strom obsahuje backend, cenník a stub /api', [true, true, true, true], [$strom('backend/public/index.php'), $strom('design_handoff_nacenime/data/nacenime-cennik.json'), $strom('design_handoff_nacenime/api.php'), $strom('design_handoff_nacenime/.htaccess')]);
$check('strom neobsahuje testy, CI, hooky ani pokyny', [false, false, false, false, false], [$strom('backend/tests'), $strom('.github'), $strom('.githooks'), $strom('CLAUDE.md'), $strom('backend/.env')]);

// Funkcie Cesty
$root = "$release/backend";
$check('rozpozná nasadenie z Gitu', [true, false], [Cesty::jeNasadenie($root), Cesty::jeNasadenie("$koren/backend")]);
$check('bez shared: chyba nasadenia', true, Cesty::chybaNasadenia($root, new Config([])) !== null);
$check('bez shared a s absolútnou cestou k DB: povolené', null, Cesty::chybaNasadenia($root, new Config(['NACENIME_DB_PATH' => '/var/data/n.sqlite'])));
$check('vývoj: nikdy chyba nasadenia', null, Cesty::chybaNasadenia("$koren/backend", new Config([])));

// HTTP: docroot = design_handoff_nacenime (ako index.html v nasadení), všetky požiadavky ide cez api.php
$port = random_int(20000, 40000);
$docroot = "$release/design_handoff_nacenime";
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $docroot, "$docroot/api.php"],
    [1 => ['file', "$web/out.log", 'w'], 2 => ['file', "$web/err.log", 'w']], $pipes, null, ['PATH' => (string) getenv('PATH')]);
register_shutdown_function(function () use ($proc, $web) {
    proc_terminate($proc);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($web, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($web);
});
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) { usleep(100000); }
$req = function (string $metoda, string $cesta, ?string $telo = null) use ($port): array {
    $ch = curl_init("http://127.0.0.1:$port$cesta");
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metoda, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]
        + ($telo !== null ? [CURLOPT_POSTFIELDS => $telo] : []));
    $o = (string) curl_exec($ch);
    $k = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [$k, json_decode($o, true)];
};
$odhad = json_encode(['typ' => 'Byt', 'obec' => 'Ružomberok', 'stav' => 'Kompletná rekonštrukcia', 'vymera' => 66]);

[$k, $j] = $req('GET', '/api/health');
$check('bez shared: API odmietne bežať (503)', [503, 'Služba nie je nakonfigurovaná.'], [$k, $j['chyba'] ?? null]);
$check('bez shared: databáza nevznikla v nahrádzanom releasi', false, file_exists("$release/backend/var/nacenime.sqlite"));
exec('php ' . escapeshellarg("$release/backend/bin/skontroluj-konfig.php") . ' 2>&1', $diag, $diagKod);
$check('bez shared: kontrola konfigurácie hlási chybu', 1, $diagKod);

// Teraz vznikne shared s .env v tvare .env.example (relatívna cesta k DB)
mkdir("$web/shared");
file_put_contents("$web/shared/.env", "NACENIME_DB_PATH=var/nacenime.sqlite\nNACENIME_MAKLER_NAME=Z priečinka shared\nNACENIME_RATE_LIMIT=0\n");
[$k, $j] = $req('POST', '/api/odhad', $odhad);
$check('so shared: výpočet funguje a .env sa číta zo shared', [200, 131000, 'Z priečinka shared'], [$k, $j['od'] ?? null, $j['makler'] ?? null]);
$check('databáza je v shared/var, nie v releasi', [true, false], [file_exists("$web/shared/var/nacenime.sqlite"), file_exists("$release/backend/var/nacenime.sqlite")]);
$check('so shared: health ok', 200, $req('GET', '/api/health')[0]);
$check('so shared: kontrola konfigurácie prejde', 0, (function () use ($release) { exec('php ' . escapeshellarg("$release/backend/bin/skontroluj-konfig.php") . ' 2>&1', $o, $k); return $k; })());
$check('nový release nájde tú istú databázu (shared prežije)', true, (function () use ($web, $koren) {
    $novy = "$web/release_20261113130000";
    exec('bash ' . escapeshellarg("$koren/deploy/zostav-deploy-strom.sh") . ' ' . escapeshellarg($novy) . ' --bez-vendor 2>&1');
    $c = Config::nacitaj(Cesty::envSubor("$novy/backend"));
    return Cesty::dbCesta($c, "$novy/backend") === "$web/shared/var/nacenime.sqlite" && is_file("$web/shared/var/nacenime.sqlite");
})());
$err = (string) @file_get_contents("$web/err.log");
$check('žiadne PHP varovania v logu', 0, preg_match_all('/Warning|Notice|Deprecated|Fatal/', $err));

exit($fail > 0 ? 1 : 0);
