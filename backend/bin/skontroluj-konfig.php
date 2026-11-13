<?php
declare(strict_types=1);
// Kontrola konfigurácie a prostredia. Exit 1 pri chybe. Tajomstvá sa nevypisujú, len či sú nastavené.
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Cesty.php';
require __DIR__ . '/../src/Diagnostika.php';

use Nacenime\{Cesty, Config, Diagnostika};

$root = dirname(__DIR__);
$vysledky = Diagnostika::skontroluj(Config::nacitaj(Cesty::envSubor($root)), $root);
$chyby = 0;
foreach ($vysledky as [$uroven, $sprava]) {
    echo sprintf("%-9s %s\n", $uroven, $sprava);
    $chyby += $uroven === 'CHYBA' ? 1 : 0;
}
echo $chyby === 0 ? "\nKonfigurácia je v poriadku.\n" : "\nChýb: $chyby\n";
exit($chyby > 0 ? 1 : 0);
