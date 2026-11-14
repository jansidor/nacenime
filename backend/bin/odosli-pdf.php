<?php
declare(strict_types=1);
// Odošle čakajúce e-maily s PDF a oznámenia o nových leadoch. Pusti z cronu každú minútu: * * * * * php backend/bin/odosli-pdf.php
require __DIR__ . '/../vendor/autoload.php';
foreach (['Config', 'Cesty', 'Storage', 'Pdf', 'Posta', 'OdosielacPdf', 'Oznamovac'] as $c) { require __DIR__ . "/../src/$c.php"; }

use Nacenime\{Cesty, Config, OdosielacPdf, Oznamovac, Pdf, Posta, Storage};

$root = dirname(__DIR__);
$config = Config::nacitaj(Cesty::envSubor($root));
$posta = new Posta($config);
$storage = new Storage(Cesty::dbCesta($config, $root));
$r = (new OdosielacPdf($storage, new Pdf(), $posta->posli(...)))->spusti($config->get('MAKLER_NAME'));
echo "Odoslané: {$r['odoslane']}, zlyhalo: {$r['zlyhalo']}, preskočené: {$r['preskocene']}\n";

// Oznámenia o leadoch: e-mail na NACENIME_LEAD_EMAIL (čiarkou oddelené), voliteľne webhook NACENIME_LEAD_WEBHOOK (n8n, CRM).
$url = $config->get('LEAD_WEBHOOK');
$o = (new Oznamovac($storage, $posta->posliText(...), $config->zoznam('LEAD_EMAIL'), $url !== '' ? Oznamovac::webhook($url) : null))->spusti();
echo "Leady oznámené: {$o['oznamene']}, zlyhalo: {$o['zlyhalo']}\n";
exit($r['zlyhalo'] + $o['zlyhalo'] > 0 ? 1 : 0);
