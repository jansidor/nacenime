<?php
declare(strict_types=1);
// Odošle čakajúce e-maily s PDF. Pusti z cronu, napr. každú minútu: * * * * * php backend/bin/odosli-pdf.php
require __DIR__ . '/../vendor/autoload.php';
foreach (['Config', 'Cesty', 'Storage', 'Pdf', 'Posta', 'OdosielacPdf'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Cesty, Config, OdosielacPdf, Pdf, Posta, Storage};

$root = dirname(__DIR__);
$config = Config::nacitaj(Cesty::envSubor($root));
$posta = new Posta($config);
$r = (new OdosielacPdf(new Storage(Cesty::dbCesta($config, $root)), new Pdf(), $posta->posli(...)))->spusti($config->get('MAKLER_NAME'));
echo "Odoslané: {$r['odoslane']}, zlyhalo: {$r['zlyhalo']}, preskočené: {$r['preskocene']}\n";
exit($r['zlyhalo'] > 0 ? 1 : 0);
