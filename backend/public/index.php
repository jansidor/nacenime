<?php
declare(strict_types=1);

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Odhad.php';
require __DIR__ . '/../src/Validator.php';
require __DIR__ . '/../src/Adresy.php';
require __DIR__ . '/../src/Storage.php';
require __DIR__ . '/../src/Api.php';

use Nacenime\{Api, Config, Odhad, Storage};

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

try {
    $root = dirname(__DIR__);
    $config = Config::nacitaj($root . '/.env');
    $cennik = $config->get('CENNIK_PATH', $root . '/../design_handoff_nacenime/data/nacenime-cennik.json');
    if ($cennik[0] !== '/') {
        $cennik = $root . '/' . $cennik;
    }
    $dbCesta = $config->get('DB_PATH', 'var/nacenime.sqlite');
    $dbCesta = $dbCesta[0] === '/' ? $dbCesta : $root . '/' . $dbCesta;
    $api = new Api(Odhad::zo_suboru($cennik), $config, new Storage($dbCesta));

    $metoda = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // IP berieme z REMOTE_ADDR; X-Forwarded-For len ak je za dôveryhodným proxy (NACENIME_TRUSTED_PROXY=1).
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($config->get('TRUSTED_PROXY') === '1' && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $telo = file_get_contents('php://input', false, null, 0, 65537) ?: '';
    // POST len ako JSON: bežný HTML formulár z cudzej stránky tak nemôže odoslať požiadavku (CSRF).
    $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($metoda === 'POST' && !str_starts_with($ct, 'application/json')) {
        [$kod, $odpoved] = [415, ['chyba' => 'Použite Content-Type: application/json.']];
    } elseif (strlen($telo) > 65536) {
        [$kod, $odpoved] = [413, ['chyba' => 'Požiadavka je príliš veľká.']];
    } else {
        $json = $telo === '' ? [] : json_decode($telo, true);
        if (!is_array($json)) {
            [$kod, $odpoved] = [400, ['chyba' => 'Neplatný JSON.']];
        } else {
            $cesta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            [$kod, $odpoved] = $api->handle($metoda, $cesta, $json, $_GET, $ip);
        }
    }
} catch (Throwable $e) {
    error_log((string) $e); // detail len do logu, nikdy do odpovede
    [$kod, $odpoved] = [500, ['chyba' => 'Chyba servera.']];
}
http_response_code($kod);
echo json_encode($odpoved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
