<?php
declare(strict_types=1);

namespace Nacenime;

/** Kontrola konfigurácie a prostredia. Nikdy nevypisuje hodnoty tajomstiev, len či sú nastavené. */
final class Diagnostika
{
    /**
     * @param list<string>|null $rozsirenia načítané rozšírenia (na testy)
     * @return list<array{0: string, 1: string}> dvojice [CHYBA|VAROVANIE|OK, správa]
     */
    public static function skontroluj(Config $c, string $root, ?array $rozsirenia = null, ?string $phpVerzia = null): array
    {
        $r = [];
        // Closures s referenciou: arrow funkcie by zachytili $r hodnotou a zápisy by sa stratili.
        $ok = function (string $m) use (&$r): void { $r[] = ['OK', $m]; };
        $varuj = function (string $m) use (&$r): void { $r[] = ['VAROVANIE', $m]; };
        $chyba = function (string $m) use (&$r): void { $r[] = ['CHYBA', $m]; };

        $verzia = $phpVerzia ?? PHP_VERSION;
        version_compare($verzia, '8.1.0', '>=') ? $ok("PHP $verzia") : $chyba("PHP $verzia je príliš staré (treba 8.1+)");
        $nacitane = array_map('strtolower', $rozsirenia ?? get_loaded_extensions());
        foreach (['mbstring', 'pdo_sqlite', 'curl', 'dom', 'gd'] as $e) {
            in_array($e, $nacitane, true) ? $ok("rozšírenie $e") : $chyba("chýba rozšírenie $e");
        }
        in_array('intl', $nacitane, true) ? $ok('rozšírenie intl') : $varuj('chýba rozšírenie intl (normalizácia obcí použije náhradu)');

        $cennik = self::cesta($c->get('CENNIK_PATH', $root . '/../design_handoff_nacenime/data/nacenime-cennik.json'), $root);
        if (!is_readable($cennik)) {
            $chyba("cenník sa nedá čítať: $cennik");
        } else {
            $j = json_decode((string) file_get_contents($cennik), true);
            is_array($j) && isset($j['cennik'], $j['lokality'], $j['prahy'], $j['zlavy']) ? $ok('cenník je platný (' . count($j['cennik']) . ' skupín, ' . count($j['lokality']) . ' obcí)') : $chyba('cenník nie je platný JSON so všetkými sekciami');
            $verejny = realpath($root . '/public') ?: '';
            if ($verejny !== '' && str_starts_with((string) realpath($cennik), $verejny . '/')) {
                $chyba('cenník je vo verejnom priečinku, presuňte ho mimo');
            }
        }

        if (($ch = Cesty::chybaNasadenia($root, $c)) !== null) {
            $chyba($ch);
        }
        $db = Cesty::dbCesta($c, $root);
        $dir = dirname($db);
        is_dir($dir) && is_writable($dir) ? $ok("priečinok databázy je zapisovateľný: $dir") : $chyba("priečinok databázy neexistuje alebo nie je zapisovateľný: $dir");
        $verejny = realpath($root . '/public') ?: '';
        if ($verejny !== '' && is_dir($dir) && str_starts_with((string) realpath($dir), $verejny)) {
            $chyba('databáza je vo verejnom priečinku');
        }

        $env = Cesty::envSubor($root);
        if (is_file($env)) {
            (fileperms($env) & 0004) ? $varuj('.env je čitateľný pre všetkých (chmod 600)') : $ok('.env má bezpečné práva');
        } else {
            $varuj('chýba .env (použijú sa premenné prostredia alebo predvolené hodnoty)');
        }

        $c->get('GOOGLE_API_KEY') !== '' ? $ok('Google API kľúč je nastavený') : $varuj('Google API kľúč nie je nastavený: našepkávač pôjde vždy cez zálohu');
        if ($c->get('SMTP_HOST') === '') {
            $varuj('SMTP nie je nastavené: e-maily s PDF sa nebudú odosielať');
        } else {
            $ok('SMTP server je nastavený');
            ($c->get('SMTP_USER') !== '' && $c->get('SMTP_PASS') === '') ? $varuj('SMTP používateľ je nastavený, ale heslo chýba') : $ok('SMTP prihlásenie je konzistentné');
            in_array($c->get('SMTP_SECURE', 'tls'), ['tls', 'ssl', 'none'], true) ? null : $chyba('NACENIME_SMTP_SECURE musí byť tls, ssl alebo none');
        }
        filter_var($c->get('MAIL_FROM', 'noreply@nacenime.sk'), FILTER_VALIDATE_EMAIL) ? $ok('odosielacia adresa je platná') : $chyba('NACENIME_MAIL_FROM nie je platný e-mail');

        $m = $c->get('MAKLER_NAME', '[MENO MAKLÉRA]');
        ($m === '' || str_contains($m, '[')) ? $varuj('meno makléra je zástupný text') : $ok('meno makléra je nastavené');
        foreach (['RATE_LIMIT' => '60', 'RETENCIA_MESIACE' => '24'] as $k => $p) {
            ctype_digit($c->get($k, $p)) ? $ok("$k = " . $c->get($k, $p)) : $chyba("NACENIME_$k musí byť celé číslo");
        }
        in_array($c->get('TRUSTED_PROXY', '0'), ['0', '1'], true) ? null : $chyba('NACENIME_TRUSTED_PROXY musí byť 0 alebo 1');
        if ($c->get('TRUSTED_PROXY') === '1') {
            $varuj('TRUSTED_PROXY=1: server musí byť dostupný len cez dôveryhodný proxy, inak sa dá obísť limit');
        }
        return $r;
    }

    private static function cesta(string $p, string $root): string
    {
        return $p !== '' && $p[0] === '/' ? $p : $root . '/' . $p;
    }
}
