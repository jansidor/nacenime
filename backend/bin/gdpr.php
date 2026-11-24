<?php
declare(strict_types=1);
// GDPR nástroj. Použitie:
//   php bin/gdpr.php export <email|telefon>   výpis uložených údajov (JSON)
//   php bin/gdpr.php zmaz <email|telefon>     zmaže kontakty a súvisiace odhady
//   php bin/gdpr.php retencia [mesiace]       zmaže údaje staršie ako N mesiacov (predvolene NACENIME_RETENCIA_MESIACE, inak 24); pustiť z cronu
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Cesty.php';
require __DIR__ . '/../src/Storage.php';

use Nacenime\{Cesty, Config, Storage};

$root = dirname(__DIR__);
$config = Config::nacitaj(Cesty::envSubor($root));
$st = new Storage(Cesty::dbCesta($config, $root));
[$prikaz, $arg] = [$argv[1] ?? '', $argv[2] ?? ''];

switch ($prikaz) {
    case 'export':
        $arg !== '' || exit("Chýba e-mail alebo telefón.\n");
        echo json_encode($st->najdiKontakty($arg), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
        break;
    case 'zmaz':
        $arg !== '' || exit("Chýba e-mail alebo telefón.\n");
        echo 'Zmazaných kontaktov: ' . $st->zmazKontakty($arg) . "\n";
        break;
    case 'retencia':
        $m = (int) ($arg !== '' ? $arg : $config->get('RETENCIA_MESIACE', '24'));
        $m > 0 || exit("Neplatný počet mesiacov.\n");
        $r = $st->zmazStarsie($m);
        echo "Zmazané: {$r['kontakty']} kontaktov, {$r['odhady']} odhadov (staršie ako $m mes.)\n";
        break;
    default:
        exit("Použitie: php bin/gdpr.php export|zmaz|retencia [hodnota]\n");
}
