<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Dáta CRM: používatelia, relácie, jednorazové odkazy, stav leadov, poznámky, história a audit.
 * Lead = kontakt z kalkulačky (žiadosť o telefonát alebo PDF so súhlasom s kontaktovaním). Rovnaká SQLite ako web.
 */
final class CrmStorage
{
    public const STAVY = ['Nový', 'Pridelený', 'Kontaktovaný', 'Stretnutie', 'Zmluva', 'Predané', 'Stratené'];
    private const LEAD_PODMIENKA = "(k.druh = 'makler' OR (k.druh = 'pdf' AND k.suhlas = 1))";

    private \PDO $pdo;

    public function __construct(Storage $storage)
    {
        $this->pdo = $storage->pdo();
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS crm_pouzivatelia (
  id INTEGER PRIMARY KEY AUTOINCREMENT, meno TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
  rola TEXT NOT NULL CHECK (rola IN ('Správca','Maklér')), heslo TEXT, aktivny INTEGER NOT NULL DEFAULT 1, vytvorene TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS crm_relacie (token TEXT PRIMARY KEY, pouzivatel_id INTEGER NOT NULL, plati_do INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS crm_odkazy (token TEXT PRIMARY KEY, pouzivatel_id INTEGER NOT NULL, plati_do INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS crm_leady (
  kontakt_id INTEGER PRIMARY KEY, cislo TEXT NOT NULL, meno TEXT, stav TEXT NOT NULL DEFAULT 'Nový',
  pridelene INTEGER, pridelene_at INTEGER, prio INTEGER NOT NULL DEFAULT 0, krok_text TEXT, krok_at INTEGER, krok_pripomenute INTEGER NOT NULL DEFAULT 0,
  cena INTEGER, datum TEXT, dovod_straty TEXT, dovod_text TEXT, max_krok INTEGER NOT NULL DEFAULT 0, prvy_kontakt INTEGER, vymazany INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS crm_poznamky (id INTEGER PRIMARY KEY AUTOINCREMENT, kontakt_id INTEGER NOT NULL, t INTEGER NOT NULL, kto TEXT NOT NULL, text TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS crm_historia (id INTEGER PRIMARY KEY AUTOINCREMENT, kontakt_id INTEGER NOT NULL, t INTEGER NOT NULL, kto TEXT NOT NULL, text TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS crm_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, t INTEGER NOT NULL, kto TEXT NOT NULL, text TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS crm_poznamky_kontakt ON crm_poznamky (kontakt_id);
CREATE INDEX IF NOT EXISTS crm_historia_kontakt ON crm_historia (kontakt_id);
CREATE INDEX IF NOT EXISTS crm_leady_pridelene ON crm_leady (pridelene);
CREATE INDEX IF NOT EXISTS crm_relacie_pouzivatel ON crm_relacie (pouzivatel_id);
CREATE INDEX IF NOT EXISTS crm_odkazy_pouzivatel ON crm_odkazy (pouzivatel_id);
SQL);
    }

    // --- používatelia, relácie, odkazy ---

    /** @return array<string, mixed>|null */
    public function pouzivatel(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM crm_pouzivatelia WHERE id = ? AND aktivny = 1');
        $s->execute([$id]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string, mixed>|null */
    public function pouzivatelPodlaEmailu(string $email): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM crm_pouzivatelia WHERE email = ? AND aktivny = 1');
        $s->execute([mb_strtolower(trim($email))]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function pocetPouzivatelov(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM crm_pouzivatelia')->fetchColumn();
    }

    public function vytvorPouzivatela(string $meno, string $email, string $rola): int
    {
        $email = mb_strtolower(trim($email));
        $s = $this->pdo->prepare('SELECT id FROM crm_pouzivatelia WHERE email = ?');
        $s->execute([$email]);
        if (($id = $s->fetchColumn()) !== false) { // odstránený člen sa môže vrátiť
            $this->pdo->prepare('UPDATE crm_pouzivatelia SET meno = ?, rola = ?, aktivny = 1, heslo = NULL WHERE id = ?')->execute([$meno, $rola, $id]);
            return (int) $id;
        }
        $this->pdo->prepare('INSERT INTO crm_pouzivatelia (meno, email, rola, vytvorene) VALUES (?, ?, ?, ?)')->execute([$meno, $email, $rola, gmdate('c')]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function tim(): array
    {
        return $this->pdo->query("SELECT id, meno, email, rola, heslo IS NOT NULL AS aktivovany FROM crm_pouzivatelia WHERE aktivny = 1 ORDER BY rola = 'Maklér', meno")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function nastavHeslo(int $id, string $heslo): void
    {
        $this->pdo->prepare('UPDATE crm_pouzivatelia SET heslo = ? WHERE id = ?')->execute([password_hash($heslo, PASSWORD_DEFAULT), $id]);
    }

    public function deaktivuj(int $id): void
    {
        $this->pdo->prepare('UPDATE crm_pouzivatelia SET aktivny = 0, heslo = NULL WHERE id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM crm_relacie WHERE pouzivatel_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM crm_odkazy WHERE pouzivatel_id = ?')->execute([$id]);
    }

    /** Vytvorí token (relácia alebo jednorazový odkaz), uloží len jeho hash. Vráti token v čitateľnej podobe. */
    public function novyToken(string $tabulka, int $pouzivatelId, int $platnostSekund): string
    {
        $tabulka = $tabulka === 'crm_relacie' ? 'crm_relacie' : 'crm_odkazy';
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->pdo->prepare("DELETE FROM $tabulka WHERE plati_do < ?")->execute([time()]);
        $this->pdo->prepare("INSERT INTO $tabulka (token, pouzivatel_id, plati_do) VALUES (?, ?, ?)")->execute([hash('sha256', $token), $pouzivatelId, time() + $platnostSekund]);
        return $token;
    }

    /** Overí token. Jednorazový odkaz sa pri overení spotrebuje. @return array<string, mixed>|null používateľ */
    public function overToken(string $tabulka, string $token, bool $spotrebuj = false): ?array
    {
        $tabulka = $tabulka === 'crm_relacie' ? 'crm_relacie' : 'crm_odkazy';
        if ($token === '' || strlen($token) > 100) {
            return null;
        }
        $h = hash('sha256', $token);
        $s = $this->pdo->prepare("SELECT pouzivatel_id FROM $tabulka WHERE token = ? AND plati_do >= ?");
        $s->execute([$h, time()]);
        $id = $s->fetchColumn();
        if ($id === false) {
            return null;
        }
        if ($spotrebuj) {
            $this->pdo->prepare("DELETE FROM $tabulka WHERE token = ?")->execute([$h]);
        }
        return $this->pouzivatel((int) $id);
    }

    /** Zruší všetky relácie používateľa okrem žiadnej (napr. po zmene hesla cez odkaz). */
    public function zrusRelacieUzivatela(int $pouzivatelId): void
    {
        $this->pdo->prepare('DELETE FROM crm_relacie WHERE pouzivatel_id = ?')->execute([$pouzivatelId]);
    }

    public function zrusRelaciu(string $token): void
    {
        $this->pdo->prepare('DELETE FROM crm_relacie WHERE token = ?')->execute([hash('sha256', $token)]);
    }

    // --- leady ---

    /** Doplní CRM záznam ku každému novému leadu z kalkulačky. */
    public function synchronizuj(): void
    {
        $nove = $this->pdo->query('SELECT k.id, k.vytvorene FROM kontakty k LEFT JOIN crm_leady c ON c.kontakt_id = k.id WHERE c.kontakt_id IS NULL AND ' . self::LEAD_PODMIENKA)->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($nove as $k) {
            $t = strtotime((string) $k['vytvorene']) ?: time();
            $this->pdo->prepare('INSERT OR IGNORE INTO crm_leady (kontakt_id, cislo) VALUES (?, ?)')->execute([$k['id'], sprintf('LD-%s-%04d', gmdate('Y', $t), $k['id'])]);
            $this->historia((int) $k['id'], 'Web', 'lead prijatý z kalkulačky', $t * 1000);
        }
    }

    /** @return list<array<string, mixed>> */
    public function leady(?int $len_pre = null, ?int $kontaktId = null): array
    {
        $sql = 'SELECT k.id AS kid, k.vytvorene, k.druh, k.telefon, k.email, k.suhlas, k.odoslane,
                o.adresa, o.psc, o.obec, o.typ, o.vymera, o.stav AS stav_neh, o.dovod, o.predstava_ceny, o.balkon, o.cena_od, o.cena_do, o.vysledok, o.bez_adresy, c.*
            FROM kontakty k JOIN crm_leady c ON c.kontakt_id = k.id LEFT JOIN odhady o ON o.id = k.odhad_id WHERE ' . self::LEAD_PODMIENKA;
        $p = [];
        if ($len_pre !== null) { $sql .= ' AND c.pridelene = ?'; $p[] = $len_pre; }
        if ($kontaktId !== null) { $sql .= ' AND k.id = ?'; $p[] = $kontaktId; }
        $s = $this->pdo->prepare($sql . ' ORDER BY k.id DESC');
        $s->execute($p);
        $r = $s->fetchAll(\PDO::FETCH_ASSOC);
        if (!$r) {
            return [];
        }
        $ids = array_column($r, 'kid');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $po = $this->pdo->prepare("SELECT kontakt_id, t, kto, text FROM crm_poznamky WHERE kontakt_id IN ($in) ORDER BY t");
        $po->execute($ids);
        $hi = $this->pdo->prepare("SELECT kontakt_id, t, kto, text FROM crm_historia WHERE kontakt_id IN ($in) ORDER BY t, id");
        $hi->execute($ids);
        $poz = $his = [];
        foreach ($po->fetchAll(\PDO::FETCH_ASSOC) as $x) { $poz[$x['kontakt_id']][] = ['t' => (int) $x['t'], 'who' => $x['kto'], 'text' => $x['text']]; }
        foreach ($hi->fetchAll(\PDO::FETCH_ASSOC) as $x) { $his[$x['kontakt_id']][] = ['t' => (int) $x['t'], 'who' => $x['kto'], 'text' => $x['text']]; }
        foreach ($r as &$l) {
            $l['notes'] = $poz[$l['kid']] ?? [];
            $l['history'] = $his[$l['kid']] ?? [];
        }
        return $r;
    }

    /** @param array<string, mixed> $zmeny */
    public function upravLead(int $kontaktId, array $zmeny): void
    {
        $povolene = ['meno', 'stav', 'pridelene', 'pridelene_at', 'prio', 'krok_text', 'krok_at', 'krok_pripomenute', 'cena', 'datum', 'dovod_straty', 'dovod_text', 'max_krok', 'prvy_kontakt', 'vymazany'];
        $zmeny = array_intersect_key($zmeny, array_flip($povolene));
        if (!$zmeny) {
            return;
        }
        $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($zmeny)));
        $this->pdo->prepare("UPDATE crm_leady SET $set WHERE kontakt_id = :id")->execute($zmeny + ['id' => $kontaktId]);
    }

    public function historia(int $kontaktId, string $kto, string $text, ?int $t = null): void
    {
        $this->pdo->prepare('INSERT INTO crm_historia (kontakt_id, t, kto, text) VALUES (?, ?, ?, ?)')->execute([$kontaktId, $t ?? self::teraz(), $kto, $text]);
    }

    public function poznamka(int $kontaktId, string $kto, string $text): void
    {
        $this->pdo->prepare('INSERT INTO crm_poznamky (kontakt_id, t, kto, text) VALUES (?, ?, ?, ?)')->execute([$kontaktId, self::teraz(), $kto, $text]);
    }

    public function audit(string $kto, string $text): void
    {
        $this->pdo->prepare('INSERT INTO crm_audit (t, kto, text) VALUES (?, ?, ?)')->execute([self::teraz(), $kto, $text]);
        $this->pdo->exec('DELETE FROM crm_audit WHERE id NOT IN (SELECT id FROM crm_audit ORDER BY id DESC LIMIT 2000)');
    }

    /** @return list<array{t: int, who: string, text: string}> */
    public function auditZoznam(int $limit = 60): array
    {
        $s = $this->pdo->prepare('SELECT t, kto AS who, text FROM crm_audit ORDER BY id DESC LIMIT ?');
        $s->execute([$limit]);
        return array_map(fn($x) => ['t' => (int) $x['t'], 'who' => $x['who'], 'text' => $x['text']], $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /** Nenávratne odstráni osobné údaje klienta, v histórii ostane záznam. */
    public function vymazKlienta(int $kontaktId): void
    {
        $this->pdo->prepare('UPDATE kontakty SET email = NULL, telefon = NULL, odoslane = CASE WHEN odoslane = 0 THEN 2 ELSE odoslane END WHERE id = ?')->execute([$kontaktId]);
        $this->pdo->prepare('UPDATE odhady SET adresa = NULL, psc = NULL, lat = NULL, lng = NULL, predstava_ceny = NULL WHERE id = (SELECT odhad_id FROM kontakty WHERE id = ?)')->execute([$kontaktId]);
        $this->pdo->prepare('DELETE FROM crm_poznamky WHERE kontakt_id = ?')->execute([$kontaktId]);
        // V histórii ostanú len udalosti, nie osobné údaje: meno klienta a voľný text dôvodu straty sa prepíšu.
        $this->pdo->prepare("UPDATE crm_historia SET text = 'meno klienta: (vymazané)' WHERE kontakt_id = ? AND text LIKE 'meno klienta:%'")->execute([$kontaktId]);
        $this->pdo->prepare("UPDATE crm_historia SET text = substr(text, 1, instr(text, 'dôvod: iné (') + length('dôvod: iné') - 1) WHERE kontakt_id = ? AND instr(text, 'dôvod: iné (') > 0")->execute([$kontaktId]);
        $this->upravLead($kontaktId, ['meno' => 'Vymazaný klient', 'vymazany' => 1, 'krok_text' => null, 'krok_at' => null, 'dovod_text' => null]);
    }

    /** Kroky, ktorých termín nastal a pripomienka ešte neodišla. @return list<array<string, mixed>> */
    public function krokyNaPripomenutie(int $teraz): array
    {
        $s = $this->pdo->prepare("SELECT c.*, k.telefon, o.typ, o.obec FROM crm_leady c JOIN kontakty k ON k.id = c.kontakt_id LEFT JOIN odhady o ON o.id = k.odhad_id
            WHERE c.krok_at IS NOT NULL AND c.krok_at <= ? AND c.krok_pripomenute = 0 AND c.stav NOT IN ('Predané','Stratené') AND c.vymazany = 0 ORDER BY c.krok_at LIMIT 50");
        $s->execute([$teraz]);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Milisekundy (frontend pracuje s časom v ms). */
    public static function teraz(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
