<?php
declare(strict_types=1);
// Odošle čakajúce e-maily s PDF. Pusti z cronu, napr. každú minútu: * * * * * php backend/bin/odosli-pdf.php
require __DIR__ . '/../vendor/autoload.php';
foreach (['Config', 'Storage', 'Pdf', 'Posta', 'OdosielacPdf'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Config, OdosielacPdf, Pdf, Posta, Storage};

$root = dirname(__DIR__);
$config = Config::nacitaj($root . '/.env');
$db = $config->get('DB_PATH', 'var/nacenime.sqlite');
$posta = new Posta($config);
$r = (new OdosielacPdf(new Storage($db[0] === '/' ? $db : $root . '/' . $db), new Pdf(), $posta->posli(...)))->spusti($config->get('MAKLER_NAME'));
echo "Odoslané: {$r['odoslane']}, zlyhalo: {$r['zlyhalo']}, preskočené: {$r['preskocene']}\n";
exit($r['zlyhalo'] > 0 ? 1 : 0);
