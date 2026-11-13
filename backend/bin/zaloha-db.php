<?php
declare(strict_types=1);
// Záloha databázy. Použitie: php bin/zaloha-db.php [priečinok=var/zalohy] [ponechať=14]. Pusti z cronu, napr. denne.
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Cesty.php';
require __DIR__ . '/../src/Zaloha.php';

use Nacenime\{Cesty, Config, Zaloha};

$root = dirname(__DIR__);
$config = Config::nacitaj(Cesty::envSubor($root));
$db = Cesty::dbCesta($config, $root);
$ciel = $argv[1] ?? (Cesty::zdielany($root) ?? $root) . '/var/zalohy';
$ponechat = (int) ($argv[2] ?? 14);
$ponechat >= 1 || exit("Počet ponechaných záloh musí byť aspoň 1.\n");
$z = Zaloha::vytvor($db, $ciel);
echo "Záloha: $z\n";
foreach (Zaloha::rotuj($ciel, $ponechat) as $s) { echo "Zmazaná stará záloha: $s\n"; }
