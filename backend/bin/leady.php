<?php
declare(strict_types=1);
// Export leadov pre makléra do CSV (stdout). Použitie: php bin/leady.php [od-datumu YYYY-MM-DD] > leady.csv
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Storage.php';
require __DIR__ . '/../src/Leady.php';

use Nacenime\{Config, Leady, Storage};

$root = dirname(__DIR__);
$config = Config::nacitaj($root . '/.env');
$db = $config->get('DB_PATH', 'var/nacenime.sqlite');
$od = $argv[1] ?? null;
if ($od !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $od) !== 1) { exit("Dátum zadajte ako YYYY-MM-DD.\n"); }
echo Leady::csv((new Storage($db[0] === '/' ? $db : $root . '/' . $db))->leady($od));
