<?php
declare(strict_types=1);

namespace Nacenime;

/** Záloha SQLite databázy: konzistentná kópia (VACUUM INTO) a rotácia starých záloh. */
final class Zaloha
{
    /** @return string cesta k vytvorenej zálohe */
    public static function vytvor(string $dbCesta, string $cielovyPriecinok, ?int $cas = null): string
    {
        if (!is_file($dbCesta)) {
            throw new \RuntimeException("Databáza neexistuje: $dbCesta");
        }
        if (!is_dir($cielovyPriecinok) && !mkdir($cielovyPriecinok, 0750, true)) {
            throw new \RuntimeException("Nedá sa vytvoriť priečinok: $cielovyPriecinok");
        }
        $ciel = rtrim($cielovyPriecinok, '/') . '/nacenime-' . date('Ymd-His', $cas ?? time()) . '.sqlite';
        if (file_exists($ciel)) {
            throw new \RuntimeException("Záloha už existuje: $ciel");
        }
        $pdo = new \PDO('sqlite:' . $dbCesta, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('VACUUM INTO ' . $pdo->quote($ciel)); // konzistentná kópia aj počas zápisov
        chmod($ciel, 0600); // obsahuje osobné údaje
        return $ciel;
    }

    /** Ponechá `$ponechat` najnovších záloh, ostatné zmaže. @return list<string> zmazané súbory */
    public static function rotuj(string $priecinok, int $ponechat): array
    {
        $subory = glob(rtrim($priecinok, '/') . '/nacenime-*.sqlite') ?: [];
        rsort($subory); // názov obsahuje dátum, takže abecedne = chronologicky
        $zmazane = [];
        foreach (array_slice($subory, max(1, $ponechat)) as $s) { // aspoň 1 vždy ostáva
            if (unlink($s)) { $zmazane[] = $s; }
        }
        return $zmazane;
    }
}
