<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * API pre CRM (crm.nacenime.sk): prihlásenie, leady, tím. Oprávnenia sa vynucujú tu, nie v rozhraní.
 * handle() vráti [HTTP kód, telo, cookie|null]; cookie = [hodnota, maxAge] (maxAge 0 = zmazať).
 */
final class Crm
{
    public const COOKIE = 'crm_relacia';
    private const RELACIA_S = 30 * 86400;
    private const ODKAZ_S = 72 * 3600;
    private const DOVODY = ['len zvedavý, nepredáva', 'predáva sám', 'iná realitná kancelária', 'nesúhlas s cenou', 'nedovolaný', 'mimo regiónu', 'iné'];
    private const TYP = ['Byt' => 'byt', 'Dom' => 'dom', 'Pozemok' => 'pozemok', 'Apartmán' => 'apartman', 'Chata' => 'chata'];

    /** @var callable(string, string, string, ?string): void */
    private $posliText;

    public function __construct(private CrmStorage $db, private Storage $storage, private Config $config, callable $posliText)
    {
        $this->posliText = $posliText;
    }

    /** @param array<string, mixed> $b @return array{0: int, 1: array<string, mixed>, 2: array{0: string, 1: int}|null} */
    public function handle(string $metoda, string $cesta, array $b, string $token, string $ip): array
    {
        $cesta = rtrim($cesta, '/');
        try {
            if ($metoda === 'POST' && $cesta === '/api/crm/prihlasenie') {
                return $this->prihlasenie($b, $ip);
            }
            if ($metoda === 'POST' && $cesta === '/api/crm/odkaz') {
                return $this->odkaz($b, $ip);
            }
            if ($metoda === 'POST' && $cesta === '/api/crm/heslo') {
                return $this->heslo($b, $ip);
            }
            $ja = $token !== '' ? $this->db->overToken('crm_relacie', $token) : null;
            if ($cesta === '/api/crm/odhlasenie' && $metoda === 'POST') {
                if ($token !== '') { $this->db->zrusRelaciu($token); }
                return [200, ['ok' => true], ['', 0]];
            }
            if ($ja === null) {
                return [401, ['chyba' => 'Prihláste sa.'], $token !== '' ? ['', 0] : null];
            }
            $this->db->synchronizuj();
            return match (true) {
                $metoda === 'GET' && $cesta === '/api/crm/data' => [200, $this->data($ja), null],
                $metoda === 'POST' && $cesta === '/api/crm/lead' => $this->lead($ja, $b),
                $metoda === 'POST' && $cesta === '/api/crm/clen' => $this->clen($ja, $b),
                $metoda === 'POST' && $cesta === '/api/crm/audit' => $this->auditKlient($ja, $b),
                default => [404, ['chyba' => 'Nenájdené.'], null],
            };
        } catch (ChybaCrm $e) {
            return [$e->kod, ['chyba' => $e->getMessage()], null];
        }
    }

    // --- prihlásenie ---

    private function obmedz(string $ip, int $max): void
    {
        if ($ip !== '' && $this->storage->zvysLimit('crm:' . hash('sha256', $ip)) > $max) {
            throw new ChybaCrm('Príliš veľa pokusov. Skúste to o minútu.', 429);
        }
    }

    /** @param array<string, mixed> $b */
    private function prihlasenie(array $b, string $ip): array
    {
        $this->obmedz($ip, 10);
        $u = $this->db->pouzivatelPodlaEmailu((string) ($b['email'] ?? ''));
        $heslo = (string) ($b['heslo'] ?? '');
        if ($u === null || $u['heslo'] === null || !password_verify($heslo, (string) $u['heslo'])) {
            return [422, ['chyba' => 'Prihlásenie sa nepodarilo. Skontrolujte e-mail a heslo.'], null];
        }
        if (password_needs_rehash((string) $u['heslo'], PASSWORD_DEFAULT)) {
            $this->db->nastavHeslo((int) $u['id'], $heslo);
        }
        $this->db->audit($u['meno'], 'prihlásenie');
        return [200, ['ok' => true], [$this->db->novyToken('crm_relacie', (int) $u['id'], self::RELACIA_S), self::RELACIA_S]];
    }

    /** Zabudnuté heslo aj prvé nastavenie. Odpoveď je rovnaká, či účet existuje alebo nie. @param array<string, mixed> $b */
    private function odkaz(array $b, string $ip): array
    {
        $this->obmedz($ip, 5);
        $email = mb_strtolower(trim((string) ($b['email'] ?? '')));
        $sprava = ['sprava' => 'Ak účet existuje, odkaz na nastavenie hesla sme poslali na zadaný e-mail.'];
        $u = $this->db->pouzivatelPodlaEmailu($email);
        // Prvý správca: kým nie je v CRM nikto, účet sa založí pre e-mail z NACENIME_CRM_ADMIN_EMAIL.
        if ($u === null && $this->db->pocetPouzivatelov() === 0 && $email !== '' && $email === mb_strtolower($this->config->get('CRM_ADMIN_EMAIL'))) {
            $id = $this->db->vytvorPouzivatela($this->config->get('CRM_ADMIN_MENO', $this->config->get('MAKLER_NAME', 'Správca')), $email, 'Správca');
            $u = $this->db->pouzivatel($id);
        }
        if ($u !== null) {
            $this->posliOdkaz($u, 'Nastavenie hesla do CRM nacenime.sk', "Dobrý deň,\n\nheslo do CRM nastavíte cez tento odkaz (platí 72 hodín):\n%s\n\nAk ste o to nežiadali, správu ignorujte.");
        }
        return [200, $sprava, null];
    }

    /** @param array<string, mixed> $b */
    private function heslo(array $b, string $ip): array
    {
        $this->obmedz($ip, 10);
        $heslo = (string) ($b['heslo'] ?? '');
        if (mb_strlen($heslo) < 12) {
            return [422, ['chyby' => ['heslo' => 'Heslo musí mať aspoň 12 znakov.']], null];
        }
        $u = $this->db->overToken('crm_odkazy', (string) ($b['token'] ?? ''), true);
        if ($u === null) {
            return [422, ['chyba' => 'Odkaz už neplatí. Požiadajte o nový cez „Zabudnuté heslo“.'], null];
        }
        $this->db->nastavHeslo((int) $u['id'], $heslo);
        $this->db->audit($u['meno'], 'nastavenie hesla');
        return [200, ['ok' => true], [$this->db->novyToken('crm_relacie', (int) $u['id'], self::RELACIA_S), self::RELACIA_S]];
    }

    /** @param array<string, mixed> $u */
    private function posliOdkaz(array $u, string $predmet, string $telo): void
    {
        $url = rtrim($this->config->get('CRM_URL', 'https://crm.nacenime.sk'), '/') . '/#heslo=' . $this->db->novyToken('crm_odkazy', (int) $u['id'], self::ODKAZ_S);
        try {
            ($this->posliText)((string) $u['email'], $predmet, sprintf($telo, $url) . "\n\nnacenime.sk", null);
        } catch (\Throwable $e) {
            error_log('CRM odkaz: ' . $e->getMessage());
        }
    }

    // --- dáta ---

    /** @param array<string, mixed> $ja @return array<string, mixed> */
    private function data(array $ja): array
    {
        $admin = $ja['rola'] === 'Správca';
        $tim = array_map(fn($m) => ['id' => 'u' . $m['id'], 'meno' => $m['meno'], 'email' => $admin || (int) $m['id'] === (int) $ja['id'] ? $m['email'] : '', 'rola' => $m['rola'], 'aktivovany' => (bool) $m['aktivovany']], $this->db->tim());
        return [
            'user' => ['id' => 'u' . $ja['id'], 'meno' => $ja['meno'], 'email' => $ja['email'], 'rola' => $ja['rola']],
            'team' => $tim,
            'leads' => array_map(fn($l) => $this->vm($l), $this->db->leady($admin ? null : (int) $ja['id'])),
            'audit' => $admin ? $this->db->auditZoznam() : [],
        ];
    }

    /** Lead v tvare, s ktorým pracuje rozhranie (podľa prototypu). @param array<string, mixed> $l @return array<string, mixed> */
    private function vm(array $l): array
    {
        $telefonat = $l['druh'] === 'makler';
        $prijaty = (strtotime((string) $l['vytvorene']) ?: time()) * 1000;
        $kontakt = $l['telefon'] ?: ($l['email'] ?: '');
        $adresa = $l['bez_adresy'] ? 'bez adresy' : trim(implode(', ', array_filter([$l['adresa'], $l['psc']])));
        $dalsie = array_filter([$l['dovod'] ? 'Dôvod: ' . $l['dovod'] : null, $l['predstava_ceny'] ? 'Predstava ceny: ' . number_format((float) $l['predstava_ceny'], 0, ',', ' ') . ' €' : null, $l['balkon'] ? 'Balkón: ' . $l['balkon'] : null]);
        return [
            'id' => 'l' . $l['kid'], 'cislo' => $l['cislo'],
            'meno' => $l['meno'] ?: ($kontakt !== '' ? $kontakt : 'Klient ' . $l['cislo']), 'menoKlienta' => $l['vymazany'] ? '' : (string) ($l['meno'] ?? ''),
            'telefon' => $l['telefon'] ?: '–', 'email' => $l['email'] ?: '–', 'suhlas' => $telefonat && (bool) $l['telefon'],
            'ziadost' => $telefonat ? 'telefonát' : 'PDF so súhlasom', 'prijaty' => $prijaty,
            'adresa' => $adresa !== '' ? $adresa : '–', 'lokalita' => $l['obec'] ?: '–', 'typ' => self::TYP[$l['typ'] ?? ''] ?? 'byt',
            'vymera' => $l['vymera'] !== null ? self::cislo((float) $l['vymera']) : '–', 'izby' => '–', 'stavNeh' => $l['stav_neh'] ?: '–', 'dalsie' => $dalsie ? implode(' · ', $dalsie) : '',
            'od' => $l['cena_od'] !== null ? (int) round($l['cena_od'] / 1000) : null, 'do' => $l['cena_do'] !== null ? (int) round($l['cena_do'] / 1000) : null,
            'vysledok' => $l['vysledok'], 'odoslany' => $telefonat ? null : $prijaty,
            'stav' => $l['stav'], 'pridelene' => $l['pridelene'] !== null ? 'u' . $l['pridelene'] : null, 'pridelenyAt' => $l['pridelene_at'] !== null ? (int) $l['pridelene_at'] : null,
            'prio' => (bool) $l['prio'], 'krokText' => $l['krok_text'] ?? '', 'krokAt' => $l['krok_at'] !== null ? (int) $l['krok_at'] : null,
            'cena' => $l['cena'] !== null ? (int) $l['cena'] : '', 'datum' => $l['datum'] ?? '', 'reason' => $l['dovod_straty'] ?? '', 'reasonText' => $l['dovod_text'] ?? '',
            'max' => (int) $l['max_krok'], 'firstContact' => $l['prvy_kontakt'] !== null ? (int) $l['prvy_kontakt'] : null, 'vymazany' => (bool) $l['vymazany'],
            'zdroj' => 'Kalkulačka', 'stranka' => 'Úvod', 'notes' => $l['notes'], 'history' => $l['history'],
        ];
    }

    // --- akcie nad leadom ---

    /** @param array<string, mixed> $ja @param array<string, mixed> $b */
    private function lead(array $ja, array $b): array
    {
        $admin = $ja['rola'] === 'Správca';
        $id = (int) preg_replace('/\D/', '', (string) ($b['id'] ?? ''));
        $l = $this->db->leady(null, $id)[0] ?? null;
        if ($l === null || (!$admin && (int) $l['pridelene'] !== (int) $ja['id'])) {
            throw new ChybaCrm('Lead neexistuje alebo k nemu nemáte prístup.', 404);
        }
        $kto = (string) $ja['meno'];
        $sprava = null;
        switch ((string) ($b['akcia'] ?? '')) {
            case 'otvorit':
                $this->db->audit($kto, 'otvoril lead ' . $l['cislo']);
                break;
            case 'stav':
                $this->zmenStav($l, (string) ($b['stav'] ?? ''), $kto, (string) ($b['dovod'] ?? ''), trim((string) ($b['text'] ?? '')), $admin);
                break;
            case 'priradit':
                $admin || throw new ChybaCrm('Prideľovať môže len správca.', 403);
                $sprava = $this->prirad($l, (string) ($b['komu'] ?? ''), $kto);
                break;
            case 'krok':
                $text = trim(mb_substr((string) ($b['text'] ?? ''), 0, 300));
                $at = filter_var($b['at'] ?? null, FILTER_VALIDATE_INT);
                ($text !== '' && $at !== false && $at > 0) || throw new ChybaCrm('Zadajte, čo urobiť, aj dátum a čas.', 422);
                $this->db->upravLead($id, ['krok_text' => $text, 'krok_at' => $at, 'krok_pripomenute' => 0]);
                $this->db->historia($id, $kto, 'ďalší krok: ' . $text . ', ' . self::cas($at));
                $komu = $l['pridelene'] !== null ? $this->db->pouzivatel((int) $l['pridelene']) : $ja;
                $sprava = 'Krok uložený. Pripomienka odíde na ' . ($komu['email'] ?? $ja['email']) . ' v čase kroku.';
                break;
            case 'poznamka':
                $text = trim(mb_substr((string) ($b['text'] ?? ''), 0, 4000));
                $text !== '' || throw new ChybaCrm('Napíšte poznámku.', 422);
                $this->db->poznamka($id, $kto, $text);
                $this->db->historia($id, $kto, 'poznámka pridaná');
                break;
            case 'predaj':
                $cena = ($b['cena'] ?? '') === '' ? null : filter_var($b['cena'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000000]]);
                $datum = (string) ($b['datum'] ?? '');
                ($cena !== false && ($datum === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum))) || throw new ChybaCrm('Neplatná cena alebo dátum.', 422);
                $this->db->upravLead($id, ['cena' => $cena, 'datum' => $datum ?: null]);
                break;
            case 'prio':
                $this->db->upravLead($id, ['prio' => !empty($b['prio']) ? 1 : 0]);
                $this->db->historia($id, $kto, !empty($b['prio']) ? 'označený ako prioritný' : 'priorita zrušená');
                break;
            case 'meno':
                $meno = trim(mb_substr((string) ($b['meno'] ?? ''), 0, 120));
                (!$l['vymazany']) || throw new ChybaCrm('Údaje klienta boli vymazané.', 422);
                $this->db->upravLead($id, ['meno' => $meno !== '' ? $meno : null]);
                $this->db->historia($id, $kto, 'meno klienta: ' . ($meno !== '' ? $meno : '–'));
                break;
            case 'vymazat':
                $admin || throw new ChybaCrm('Vymazať údaje môže len správca.', 403);
                $this->db->vymazKlienta($id);
                $this->db->historia($id, $kto, 'údaje klienta vymazané');
                $this->db->audit($kto, 'vymazal údaje klienta v ' . $l['cislo']);
                $sprava = 'Údaje klienta boli vymazané.';
                break;
            default:
                throw new ChybaCrm('Neznáma akcia.', 422);
        }
        return [200, ['lead' => $this->vm($this->db->leady(null, $id)[0]), 'sprava' => $sprava], null];
    }

    /** @param array<string, mixed> $l */
    private function zmenStav(array $l, string $ns, string $kto, string $dovod, string $text, bool $admin): void
    {
        $id = (int) $l['kid'];
        in_array($ns, CrmStorage::STAVY, true) || throw new ChybaCrm('Neznámy stav.', 422);
        if ($ns === $l['stav']) {
            return;
        }
        if ($ns === 'Stratené') {
            in_array($dovod, self::DOVODY, true) || throw new ChybaCrm('Vyberte dôvod straty.', 422);
            ($dovod !== 'iné' || $text !== '') || throw new ChybaCrm('Popíšte dôvod.', 422);
        } elseif ($l['pridelene'] === null && $ns !== 'Nový') {
            throw new ChybaCrm('Najprv lead pridelte makléra.', 422);
        } elseif ($l['pridelene'] !== null && $ns === 'Nový') {
            throw new ChybaCrm('Lead s maklérom nemôže byť v stave Nový. Zrušte pridelenie.', 422);
        }
        $idx = array_search($ns, CrmStorage::STAVY, true);
        $z = ['stav' => $ns];
        if ($idx < 6) { $z['max_krok'] = max((int) $l['max_krok'], $idx); }
        if ($idx >= 2 && $idx < 6 && $l['prvy_kontakt'] === null) { $z['prvy_kontakt'] = CrmStorage::teraz(); }
        if ($ns === 'Predané' && !$l['datum']) { $z['datum'] = gmdate('Y-m-d'); }
        if ($ns === 'Stratené') { $z['dovod_straty'] = $dovod; $z['dovod_text'] = $dovod === 'iné' ? mb_substr($text, 0, 300) : null; }
        else { $z['dovod_straty'] = null; $z['dovod_text'] = null; }
        $this->db->upravLead($id, $z);
        $this->db->historia($id, $kto, 'stav ' . $l['stav'] . ' → ' . $ns . ($ns === 'Stratené' ? ', dôvod: ' . $dovod . ($dovod === 'iné' ? ' (' . $text . ')' : '') : ''));
    }

    /** @param array<string, mixed> $l */
    private function prirad(array $l, string $komu, string $kto): ?string
    {
        $id = (int) $l['kid'];
        $uid = $komu === '' ? null : (int) preg_replace('/\D/', '', $komu);
        if ($uid === ($l['pridelene'] !== null ? (int) $l['pridelene'] : null)) {
            return null;
        }
        if ($uid === null) {
            in_array($l['stav'], ['Nový', 'Pridelený'], true) || throw new ChybaCrm('Lead v riešení sa nedá nechať bez makléra. Prideľte iného.', 422);
            $z = ['pridelene' => null, 'pridelene_at' => null];
            if ($l['stav'] === 'Pridelený') { $z['stav'] = 'Nový'; }
            $this->db->upravLead($id, $z);
            $this->db->historia($id, $kto, 'pridelenie zrušené');
            if ($l['stav'] === 'Pridelený') { $this->db->historia($id, $kto, 'stav Pridelený → Nový'); }
            return null;
        }
        $m = $this->db->pouzivatel($uid);
        ($m !== null && $m['rola'] === 'Maklér') || throw new ChybaCrm('Neznámy maklér.', 422);
        $z = ['pridelene' => $uid, 'pridelene_at' => CrmStorage::teraz()];
        if ($l['stav'] === 'Nový') { $z['stav'] = 'Pridelený'; $z['max_krok'] = max((int) $l['max_krok'], 1); }
        $this->db->upravLead($id, $z);
        $this->db->historia($id, $kto, 'pridelený: ' . $m['meno']);
        if ($l['stav'] === 'Nový') { $this->db->historia($id, $kto, 'stav Nový → Pridelený'); }
        $popis = trim(($l['typ'] ?? 'Nehnuteľnosť') . ($l['vymera'] !== null ? ', ' . ((float) $l['vymera'] + 0) . ' m²' : '') . ($l['obec'] ? ', ' . $l['obec'] : ''));
        try {
            ($this->posliText)((string) $m['email'], 'Pridelený lead ' . $l['cislo'] . ': ' . $popis,
                "Dobrý deň,\n\n$kto vám pridelil lead {$l['cislo']} ($popis)." . ($l['druh'] === 'makler' ? "\nKlient čaká na telefonát: " . ($l['telefon'] ?: '–') : '')
                . "\n\nOtvoriť v CRM: " . rtrim($this->config->get('CRM_URL', 'https://crm.nacenime.sk'), '/') . "\n\nnacenime.sk", null);
            return 'Pridelený: ' . $m['meno'] . '. E-mail s upozornením odišiel na ' . $m['email'] . '.';
        } catch (\Throwable $e) {
            error_log('CRM pridelenie e-mail: ' . $e->getMessage());
            return 'Pridelený: ' . $m['meno'] . '. E-mail s upozornením sa nepodarilo odoslať.';
        }
    }

    // --- tím ---

    /** @param array<string, mixed> $ja @param array<string, mixed> $b */
    private function clen(array $ja, array $b): array
    {
        $ja['rola'] === 'Správca' || throw new ChybaCrm('Tím spravuje len správca.', 403);
        $kto = (string) $ja['meno'];
        if (($b['akcia'] ?? '') === 'pozvat') {
            $meno = trim(mb_substr((string) ($b['meno'] ?? ''), 0, 120));
            $email = mb_strtolower(trim((string) ($b['email'] ?? '')));
            ($meno !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) || throw new ChybaCrm('Zadajte meno a platný e-mail.', 422);
            $this->db->pouzivatelPodlaEmailu($email) === null || throw new ChybaCrm('Tento e-mail už v tíme je.', 422);
            $rola = ($b['rola'] ?? '') === 'Správca' ? 'Správca' : 'Maklér';
            $u = $this->db->pouzivatel($this->db->vytvorPouzivatela($meno, $email, $rola));
            $this->posliOdkaz($u, 'Pozvánka do CRM nacenime.sk', "Dobrý deň,\n\n$kto vás pozval do CRM nacenime.sk (leady z kalkulačky).\nHeslo si nastavíte cez tento odkaz (platí 72 hodín):\n%s");
            $this->db->audit($kto, 'pozvaný ' . mb_strtolower($rola) . ' ' . $meno);
            return [200, ['team' => $this->data($ja)['team'], 'sprava' => 'Pozvánka odišla na ' . $email . '.'], null];
        }
        if (($b['akcia'] ?? '') === 'odstranit') {
            $id = (int) preg_replace('/\D/', '', (string) ($b['id'] ?? ''));
            $m = $this->db->pouzivatel($id);
            ($m !== null && $id !== (int) $ja['id']) || throw new ChybaCrm('Tohto člena nemožno odstrániť.', 422);
            $aktivne = array_filter($this->db->leady($id), fn($l) => !in_array($l['stav'], ['Predané', 'Stratené'], true));
            $nove = null;
            if ($aktivne) {
                $to = (int) preg_replace('/\D/', '', (string) ($b['komu'] ?? ''));
                $nove = $to !== $id ? $this->db->pouzivatel($to) : null;
                ($nove !== null && $nove['rola'] === 'Maklér') || throw new ChybaCrm('Vyberte makléra, ktorému prehodiť aktívne leady.', 422);
                foreach ($aktivne as $l) {
                    $this->db->upravLead((int) $l['kid'], ['pridelene' => $to, 'pridelene_at' => CrmStorage::teraz()]);
                    $this->db->historia((int) $l['kid'], $kto, 'prehodené z ' . $m['meno'] . ' na ' . $nove['meno']);
                }
            }
            $this->db->deaktivuj($id);
            $this->db->audit($kto, 'odstránený ' . mb_strtolower((string) $m['rola']) . ' ' . $m['meno'] . ($nove ? ', leady prehodené na ' . $nove['meno'] : ''));
            return [200, $this->data($ja) + ['sprava' => $m['meno'] . ' bol odstránený z tímu.'], null];
        }
        throw new ChybaCrm('Neznáma akcia.', 422);
    }

    /** Export CSV robí prehliadač, server ho len zapíše do auditu. @param array<string, mixed> $ja @param array<string, mixed> $b */
    private function auditKlient(array $ja, array $b): array
    {
        if (($b['akcia'] ?? '') === 'export') {
            $this->db->audit((string) $ja['meno'], 'export CSV (' . max(0, (int) ($b['pocet'] ?? 0)) . ' leadov)');
        }
        return [200, ['ok' => true], null];
    }

    // --- pripomienky z cronu ---

    /** Pošle pripomienky krokov, ktorých čas nastal. @return array{odoslane: int, zlyhalo: int} */
    public function pripomienky(?int $teraz = null): array
    {
        $r = ['odoslane' => 0, 'zlyhalo' => 0];
        $url = rtrim($this->config->get('CRM_URL', 'https://crm.nacenime.sk'), '/');
        foreach ($this->db->krokyNaPripomenutie($teraz ?? CrmStorage::teraz()) as $k) {
            $komu = $k['pridelene'] !== null ? [$this->db->pouzivatel((int) $k['pridelene'])] : array_filter($this->db->tim(), fn($m) => $m['rola'] === 'Správca');
            try {
                foreach (array_filter($komu) as $m) {
                    ($this->posliText)((string) $m['email'], 'Pripomienka: ' . $k['krok_text'] . ' (' . $k['cislo'] . ')',
                        "Dobrý deň,\n\nnastal čas ďalšieho kroku pri leade {$k['cislo']}" . ($k['typ'] ? " ({$k['typ']}, {$k['obec']})" : '') . ":\n{$k['krok_text']}, " . self::cas((int) $k['krok_at'])
                        . ($k['telefon'] ? "\nTelefón klienta: {$k['telefon']}" : '') . "\n\nOtvoriť v CRM: $url\n\nnacenime.sk", null);
                }
                $this->db->upravLead((int) $k['kontakt_id'], ['krok_pripomenute' => 1]);
                $r['odoslane']++;
            } catch (\Throwable $e) {
                error_log('CRM pripomienka ' . $k['cislo'] . ': ' . $e->getMessage());
                $r['zlyhalo']++;
            }
        }
        return $r;
    }

    private static function cislo(float $n): int|float
    {
        return fmod($n, 1.0) === 0.0 ? (int) $n : $n;
    }

    private static function cas(int $ms): string
    {
        return (new \DateTimeImmutable('@' . intdiv($ms, 1000)))->setTimezone(new \DateTimeZone('Europe/Bratislava'))->format('j. n. Y H:i');
    }
}

final class ChybaCrm extends \RuntimeException
{
    public function __construct(string $sprava, public readonly int $kod = 422)
    {
        parent::__construct($sprava);
    }
}
