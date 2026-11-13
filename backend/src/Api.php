<?php
declare(strict_types=1);

namespace Nacenime;

/** HTTP-nezávislá aplikačná vrstva: handle() vráti [HTTP kód, telo]. Ľahko sa testuje. */
final class Api
{
    private const POZNAMKA = [
        'Byt' => 'Vyššiu cenu dosahujú byty po rekonštrukcii a s dobrou prípravou predaja.',
        'Apartmán' => 'Vyššiu cenu dosahujú apartmány po rekonštrukcii a s dobrou prípravou predaja.',
        'Dom' => 'Vyššiu cenu dosahujú domy po rekonštrukcii a s dobrou prípravou predaja.',
        'Chata' => 'Vyššiu cenu dosahujú chaty po rekonštrukcii a s dobrou prípravou predaja.',
        'Pozemok' => 'Vyššiu cenu dosahujú pozemky so sieťami a prístupovou cestou.',
    ];

    /** @var list<string> */
    private array $typy;
    /** @var list<string> */
    private array $stavy;

    public function __construct(private Odhad $odhad, private Config $config, private ?Storage $storage = null, private ?Adresy $adresy = null)
    {
        $this->storage ??= new Storage($config->get('DB_PATH', dirname(__DIR__) . '/var/nacenime.sqlite'));
        $this->adresy ??= new Adresy($odhad, $config, null, $this->storage);
        $this->typy = $odhad->typy();
        $this->stavy = $odhad->stavy();
    }

    /** @param array<string, mixed> $body @return array{0: int, 1: array<string, mixed>} */
    public function handle(string $method, string $path, array $body = [], array $query = [], string $ip = ''): array
    {
        $path = rtrim($path, '/') ?: '/';
        if ($path === '/api/health') {
            return $method === 'GET' ? $this->health() : $this->chyba(405, 'Použite GET.'); // mimo limitu, pre monitoring
        }
        $limit = (int) $this->config->get('RATE_LIMIT', '60');
        if ($ip !== '' && $limit > 0 && $this->storage->zvysLimit(hash('sha256', $ip)) > $limit) {
            return [429, ['chyba' => 'Príliš veľa požiadaviek. Skúste to o chvíľu.']];
        }
        return match (true) {
            $path === '/api/odhad' => $method === 'POST' ? $this->odhad($body) : $this->chyba(405, 'Použite POST.'),
            $path === '/api/adresa' => $method === 'GET' ? $this->adresy->navrhy(self::param($query, 'q'), self::param($query, 'token')) : $this->chyba(405, 'Použite GET.'),
            $path === '/api/adresa/detail' => $method === 'GET' ? $this->adresy->detail(self::param($query, 'id'), self::param($query, 'token')) : $this->chyba(405, 'Použite GET.'),
            $path === '/api/obec' => $method === 'GET' ? $this->adresy->obec(self::param($query, 'obec')) : $this->chyba(405, 'Použite GET.'),
            $path === '/api/kontakt/pdf' => $method === 'POST' ? $this->pdf($body) : $this->chyba(405, 'Použite POST.'),
            $path === '/api/kontakt/makler' => $method === 'POST' ? $this->makler($body) : $this->chyba(405, 'Použite POST.'),
            $path === '/api/kontakt/mimo-regionu' => $method === 'POST' ? $this->mimoRegionu($body) : $this->chyba(405, 'Použite POST.'),
            default => $this->chyba(404, 'Nenájdené.'),
        };
    }

    /** @param array<string, mixed> $body @return array{0: int, 1: array<string, mixed>} */
    private function odhad(array $body): array
    {
        [$v, $chyby] = Validator::odhad($body, $this->typy, $this->stavy);
        if ($chyby) {
            return [422, ['chyby' => $chyby]];
        }
        $r = $this->odhad->odhad($v, $this->config->zoznam('TYPY_BEZ_ROZPATIA'));
        if ($r['stav'] === 'ok') {
            $r['poznamka'] = self::POZNAMKA[$v['typ']];
        }
        // Meno makléra je jedna hodnota z konfigurácie.
        $r['makler'] = $this->config->get('MAKLER_NAME', '[MENO MAKLÉRA]');
        if ($r['stav'] !== 'mimo regiónu') {
            $r['odhad_id'] = $this->uloz($body, $v, $r);
        }
        return [200, $r];
    }

    /** @param array<string, mixed> $body @param array<string, mixed> $v @param array<string, mixed> $r */
    private function uloz(array $body, array $v, array $r): int
    {
        $a = is_array($body['adresa'] ?? null) ? $body['adresa'] : [];
        $txt = fn(mixed $x, int $max): ?string => is_string($x) && trim($x) !== '' ? mb_substr(trim($x), 0, $max) : null;
        $num = fn(mixed $x): ?float => is_numeric($x) && is_finite((float) $x) ? (float) $x : null;
        $predstava = Validator::cislo($body['predstava_ceny'] ?? null);
        return $this->storage->ulozOdhad([
            'adresa' => $txt($a['ulica'] ?? null, 200), 'psc' => $txt($a['psc'] ?? null, 10), 'obec' => $v['obec'],
            'lat' => $num($a['lat'] ?? null), 'lng' => $num($a['lng'] ?? null),
            'bez_adresy' => (int) !empty($a['bez_adresy']), 'zo_zalohy' => (int) !empty($a['zo_zalohy']),
            'dovod' => $txt($body['dovod'] ?? null, 60), 'typ' => $v['typ'], 'vymera' => $v['vymera'], 'stav' => $v['stav'],
            'balkon' => $txt($body['balkon'] ?? null, 10), 'predstava_ceny' => $predstava === null ? null : (int) $predstava,
            'vysledok' => $r['stav'], 'cena_od' => $r['od'], 'cena_do' => $r['do'], 'pocet' => $r['pocet'], 'veta' => $r['veta'],
        ]);
    }

    /** @param array<string, mixed> $b @return array{0: int, 1: array<string, mixed>} */
    private function pdf(array $b): array
    {
        if ($this->robot($b)) {
            return [200, ['sprava' => 'Odhad vám pošleme na e-mail.']];
        }
        $email = Validator::email($b['email'] ?? null);
        if ($email === null) {
            return [422, ['chyby' => ['email' => 'Zadajte e-mail.']]];
        }
        $id = $this->odhadId($b);
        if ($id === false) {
            return [422, ['chyby' => ['odhad_id' => 'Neznámy odhad.']]];
        }
        // Súhlas je nepovinný a predvolene nezaškrtnutý: uloží sa len pri výslovnom true.
        $this->storage->ulozKontakt('pdf', $email, null, ($b['suhlas'] ?? false) === true, $id);
        return [200, ['sprava' => 'Odhad vám pošleme na e-mail.']];
    }

    /** @param array<string, mixed> $b @return array{0: int, 1: array<string, mixed>} */
    private function makler(array $b): array
    {
        if ($this->robot($b)) {
            return [200, ['sprava' => 'Ďakujeme, ozveme sa vám.']];
        }
        $tel = Validator::telefon($b['telefon'] ?? null);
        if ($tel === null) {
            return [422, ['chyby' => ['telefon' => 'Zadajte telefónne číslo.']]];
        }
        $id = $this->odhadId($b);
        if ($id === false) {
            return [422, ['chyby' => ['odhad_id' => 'Neznámy odhad.']]];
        }
        $this->storage->ulozKontakt('makler', null, $tel, false, $id);
        return [200, ['sprava' => 'Ďakujeme, ozveme sa vám.']];
    }

    /** @param array<string, mixed> $b @return array{0: int, 1: array<string, mixed>} */
    private function mimoRegionu(array $b): array
    {
        if ($this->robot($b)) {
            return [200, ['sprava' => 'Ďakujeme, dáme vám vedieť.']];
        }
        $email = Validator::email($b['email'] ?? null);
        if ($email === null) {
            return [422, ['chyby' => ['email' => 'Zadajte e-mail.']]];
        }
        $this->storage->ulozKontakt('mimo_regionu', $email, null, false, null);
        return [200, ['sprava' => 'Ďakujeme, dáme vám vedieť.']];
    }

    /** Honeypot: skryté pole `web` vypĺňajú len roboty. Robotovi sa tvárime, že to prešlo, nič neukladáme. */
    private function robot(array $b): bool
    {
        return isset($b['web']) && $b['web'] !== '';
    }

    /** @param array<string, mixed> $b @return int|null|false null = nezadané, false = neexistuje */
    private function odhadId(array $b): int|null|false
    {
        if (!isset($b['odhad_id'])) {
            return null;
        }
        $id = is_int($b['odhad_id']) ? $b['odhad_id'] : null;
        return $id !== null && $this->storage->odhadExistuje($id) ? $id : false;
    }

    /** Query parameter ako reťazec; polia (`?q[]=x`) a iné typy sa berú ako prázdne. @param array<string, mixed> $query */
    private static function param(array $query, string $kluc): string
    {
        return is_string($query[$kluc] ?? null) ? $query[$kluc] : '';
    }

    /** Bez detailov: monitoring potrebuje len ok/chyba. @return array{0: int, 1: array<string, mixed>} */
    private function health(): array
    {
        try {
            $ok = $this->odhad->typy() !== [] && $this->storage->ping();
        } catch (\Throwable $e) {
            error_log('health: ' . $e->getMessage());
            $ok = false;
        }
        return $ok ? [200, ['stav' => 'ok']] : [503, ['stav' => 'chyba']];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function chyba(int $kod, string $sprava): array
    {
        return [$kod, ['chyba' => $sprava]];
    }
}
