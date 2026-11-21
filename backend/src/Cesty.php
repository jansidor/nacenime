<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Cesty pre nasadenie z Gitu (Hostcreators): kód leží v …/release_<čas>/backend a každé nové nasadenie ho nahradí,
 * preto `.env` a databáza patria do súrodeneckého priečinka …/shared (mimo kódu). Pri bežnom vývoji sa nič nemení.
 */
final class Cesty
{
    /** Skutočná cesta (bez `..` a symlinkov): stub v inom priečinku načíta index.php cez `../backend/public`. */
    private static function skutocna(string $root): string
    {
        return str_replace('\\', '/', realpath($root) ?: $root);
    }

    /** Beží kód z nasadenia (…/release_X/backend)? */
    public static function jeNasadenie(string $root): bool
    {
        return preg_match('#/release_[^/]+/backend$#', self::skutocna($root)) === 1;
    }

    /** Priečinok `shared` vedľa releaseov, ak existuje a ide o nasadenie. */
    public static function zdielany(string $root): ?string
    {
        $s = dirname(self::skutocna($root), 2) . '/shared';
        return self::jeNasadenie($root) && is_dir($s) ? $s : null;
    }

    /** Lokálny `.env`, inak `shared/.env` (pri nasadení), inak lokálna cesta (aj keď neexistuje). */
    public static function envSubor(string $root): string
    {
        if (is_file($root . '/.env')) {
            return $root . '/.env';
        }
        $s = self::zdielany($root);
        return $s !== null && is_file($s . '/.env') ? $s . '/.env' : $root . '/.env';
    }

    /** Cesta k databáze. Relatívna cesta sa vzťahuje na `shared` (nasadenie) alebo na backend (vývoj). */
    public static function dbCesta(Config $c, string $root): string
    {
        $p = $c->get('DB_PATH', 'var/nacenime.sqlite');
        if ($p === ':memory:' || ($p !== '' && $p[0] === '/')) {
            return $p;
        }
        return (self::zdielany($root) ?? $root) . '/' . $p;
    }

    /** Dôvod, prečo nasadenie nie je pripravené (null = v poriadku). Chráni pred databázou vo verejnom priečinku. */
    public static function chybaNasadenia(string $root, Config $c): ?string
    {
        if (!self::jeNasadenie($root)) {
            return null;
        }
        $p = $c->get('DB_PATH', 'var/nacenime.sqlite');
        $absolutna = $p !== '' && $p[0] === '/';
        if (!$absolutna && self::zdielany($root) === null) {
            return 'Nasadenie z Gitu: chýba priečinok shared (' . dirname(self::skutocna($root), 2) . '/shared) pre .env a databázu. '
                . 'Bez neho by databáza skončila v nahrádzanom a verejnom priečinku.';
        }
        return null;
    }
}
