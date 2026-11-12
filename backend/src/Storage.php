<?php
declare(strict_types=1);

namespace Nacenime;

/** Ukladanie odhadov a kontaktov do SQLite (PDO, len pripravené dotazy). */
final class Storage
{
    private \PDO $pdo;

    public function __construct(string $cesta)
    {
        if ($cesta !== ':memory:' && !is_dir(dirname($cesta))) {
            mkdir(dirname($cesta), 0750, true);
        }
        $this->pdo = new \PDO('sqlite:' . $cesta, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
        // WAL: čitatelia neblokujú zapisovateľa (pri súbežných požiadavkách rýchlejšie); čakanie na zámok najviac 5 s.
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS odhady (
  id INTEGER PRIMARY KEY AUTOINCREMENT, vytvorene TEXT NOT NULL,
  adresa TEXT, psc TEXT, obec TEXT NOT NULL, lat REAL, lng REAL, bez_adresy INTEGER NOT NULL DEFAULT 0, zo_zalohy INTEGER NOT NULL DEFAULT 0,
  dovod TEXT, typ TEXT NOT NULL, vymera REAL NOT NULL, stav TEXT, balkon TEXT, predstava_ceny INTEGER,
  vysledok TEXT NOT NULL, cena_od INTEGER, cena_do INTEGER, pocet INTEGER, veta TEXT
);
CREATE TABLE IF NOT EXISTS limity (kluc TEXT NOT NULL, okno INTEGER NOT NULL, pocet INTEGER NOT NULL, PRIMARY KEY (kluc, okno));
CREATE TABLE IF NOT EXISTS kontakty (
  id INTEGER PRIMARY KEY AUTOINCREMENT, vytvorene TEXT NOT NULL,
  druh TEXT NOT NULL CHECK (druh IN ('pdf','makler','mimo_regionu')),
  email TEXT, telefon TEXT, suhlas INTEGER NOT NULL DEFAULT 0, odhad_id INTEGER REFERENCES odhady(id),
  odoslane INTEGER NOT NULL DEFAULT 0, pokusy INTEGER NOT NULL DEFAULT 0
);
SQL);
        $this->doplnStlpec('odhady', 'veta', 'TEXT');
        $this->doplnStlpec('kontakty', 'pokusy', 'INTEGER NOT NULL DEFAULT 0');
    }

    /** Jednoduchá migrácia pre databázy vytvorené staršou verziou schémy. */
    private function doplnStlpec(string $tabulka, string $stlpec, string $typ): void
    {
        foreach ($this->pdo->query("PRAGMA table_info($tabulka)") as $r) {
            if ($r['name'] === $stlpec) {
                return;
            }
        }
        $this->pdo->exec("ALTER TABLE $tabulka ADD COLUMN $stlpec $typ");
    }

    /** @param array<string, mixed> $d */
    public function ulozOdhad(array $d): int
    {
        $s = $this->pdo->prepare('INSERT INTO odhady (vytvorene, adresa, psc, obec, lat, lng, bez_adresy, zo_zalohy, dovod, typ, vymera, stav, balkon, predstava_ceny, vysledok, cena_od, cena_do, pocet, veta)
            VALUES (:vytvorene, :adresa, :psc, :obec, :lat, :lng, :bez_adresy, :zo_zalohy, :dovod, :typ, :vymera, :stav, :balkon, :predstava_ceny, :vysledok, :cena_od, :cena_do, :pocet, :veta)');
        $s->execute($d + ['vytvorene' => gmdate('c')]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Zvýši počítadlo pre kľúč v minútovom okne a vráti nový počet; staré okná maže. */
    public function zvysLimit(string $kluc, ?int $cas = null): int
    {
        $okno = intdiv($cas ?? time(), 60);
        $this->pdo->prepare('DELETE FROM limity WHERE okno < ?')->execute([$okno - 1]);
        $this->pdo->prepare('INSERT INTO limity (kluc, okno, pocet) VALUES (?, ?, 1) ON CONFLICT (kluc, okno) DO UPDATE SET pocet = pocet + 1')->execute([$kluc, $okno]);
        $s = $this->pdo->prepare('SELECT pocet FROM limity WHERE kluc = ? AND okno = ?');
        $s->execute([$kluc, $okno]);
        return (int) $s->fetchColumn();
    }

    /** Kontrola dostupnosti databázy (zápis aj čítanie). */
    public function ping(): bool
    {
        $this->pdo->exec('DELETE FROM limity WHERE okno < 0'); // overí aj zápis
        return (int) $this->pdo->query('SELECT 1')->fetchColumn() === 1;
    }

    /** @return array<string, mixed>|null */
    public function odhad(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM odhady WHERE id = ?');
        $s->execute([$id]);
        return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /** E-maily s PDF, ktoré ešte neboli odoslané. @return list<array<string, mixed>> */
    public function cakajucePdf(int $maxPokusov, int $limit = 50): array
    {
        $s = $this->pdo->prepare("SELECT * FROM kontakty WHERE druh = 'pdf' AND odoslane = 0 AND pokusy < ? ORDER BY id LIMIT ?");
        $s->execute([$maxPokusov, $limit]);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @param int $stav 1 = odoslané, 2 = preskočené (kontakt bez odhadu) */
    public function oznacOdoslane(int $kontaktId, int $stav = 1): void
    {
        $this->pdo->prepare('UPDATE kontakty SET odoslane = ? WHERE id = ?')->execute([$stav, $kontaktId]);
    }

    public function pokusZlyhal(int $kontaktId): void
    {
        $this->pdo->prepare('UPDATE kontakty SET pokusy = pokusy + 1 WHERE id = ?')->execute([$kontaktId]);
    }

    public function odhadExistuje(int $id): bool
    {
        $s = $this->pdo->prepare('SELECT 1 FROM odhady WHERE id = ?');
        $s->execute([$id]);
        return (bool) $s->fetchColumn();
    }

    /**
     * Uloží kontakt. Rovnaký kontakt (e-mail bez ohľadu na veľkosť písmen, telefón podľa číslic) pre ten istý odhad
     * a druh v okne `$oknoSekund` sa neukladá znova (dvojklik, opakované odoslanie). Ak sa pri opakovaní
     * pridá súhlas, doplní sa k existujúcemu záznamu. Vráti id záznamu.
     */
    public function ulozKontakt(string $druh, ?string $email, ?string $telefon, bool $suhlas, ?int $odhadId, int $oknoSekund = 600, ?int $teraz = null): int
    {
        $teraz ??= time();
        $s = $this->pdo->prepare('SELECT id, email, telefon, suhlas FROM kontakty WHERE druh = :d AND odhad_id IS :o AND vytvorene >= :od');
        $s->execute(['d' => $druh, 'o' => $odhadId, 'od' => gmdate('c', $teraz - $oknoSekund)]);
        $kluc = fn(?string $e, ?string $t): string => $e !== null ? 'e:' . mb_strtolower($e) : 't:' . preg_replace('/\D/', '', (string) $t);
        foreach ($s->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            if ($kluc($r['email'], $r['telefon']) === $kluc($email, $telefon)) {
                if ($suhlas && !(int) $r['suhlas']) {
                    $this->pdo->prepare('UPDATE kontakty SET suhlas = 1 WHERE id = ?')->execute([$r['id']]);
                }
                return (int) $r['id'];
            }
        }
        $i = $this->pdo->prepare('INSERT INTO kontakty (vytvorene, druh, email, telefon, suhlas, odhad_id) VALUES (?, ?, ?, ?, ?, ?)');
        $i->execute([gmdate('c', $teraz), $druh, $email, $telefon, (int) $suhlas, $odhadId]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function kontakty(string $druh): array
    {
        $s = $this->pdo->prepare('SELECT * FROM kontakty WHERE druh = ? ORDER BY id');
        $s->execute([$druh]);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Kontakty podľa e-mailu alebo telefónu (export na žiadosť o prístup k údajom). @return list<array<string, mixed>> */
    public function najdiKontakty(string $hodnota): array
    {
        $s = $this->pdo->prepare('SELECT k.*, o.adresa, o.psc, o.obec, o.typ, o.vymera, o.cena_od, o.cena_do FROM kontakty k LEFT JOIN odhady o ON o.id = k.odhad_id WHERE k.email = :h OR k.telefon = :h ORDER BY k.id');
        $s->execute(['h' => $hodnota]);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Leady pre makléra: žiadosti o telefonát a PDF so súhlasom s kontaktovaním. @return list<array<string, mixed>> */
    public function leady(?string $od = null): array
    {
        $s = $this->pdo->prepare("SELECT k.id, k.vytvorene, k.druh, k.telefon, k.email, o.adresa, o.psc, o.obec, o.typ, o.vymera, o.stav, o.cena_od, o.cena_do, o.dovod, o.predstava_ceny
            FROM kontakty k LEFT JOIN odhady o ON o.id = k.odhad_id
            WHERE (k.druh = 'makler' OR (k.druh = 'pdf' AND k.suhlas = 1)) AND k.vytvorene >= ? ORDER BY k.id");
        $s->execute([$od ?? '0000']);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Zmaže kontakty podľa e-mailu/telefónu aj odhady, na ktoré už neodkazuje iný kontakt. Vráti počet zmazaných kontaktov. */
    public function zmazKontakty(string $hodnota): int
    {
        return $this->vTransakcii(function () use ($hodnota): int {
            $ids = $this->pdo->prepare('SELECT DISTINCT odhad_id FROM kontakty WHERE (email = :h OR telefon = :h) AND odhad_id IS NOT NULL');
            $ids->execute(['h' => $hodnota]);
            $odhady = $ids->fetchAll(\PDO::FETCH_COLUMN);
            $n = $this->pdo->prepare('DELETE FROM kontakty WHERE email = :h OR telefon = :h');
            $n->execute(['h' => $hodnota]);
            // Len odhady zmazaného kontaktu a len ak na ne neodkazuje iný kontakt.
            $d = $this->pdo->prepare('DELETE FROM odhady WHERE id = ? AND id NOT IN (SELECT odhad_id FROM kontakty WHERE odhad_id IS NOT NULL)');
            foreach ($odhady as $id) {
                $d->execute([$id]);
            }
            return $n->rowCount();
        });
    }

    /** Retencia: zmaže kontakty a odhady staršie ako N mesiacov. @return array{kontakty: int, odhady: int} */
    public function zmazStarsie(int $mesiace, ?int $teraz = null): array
    {
        $hranica = gmdate('c', strtotime("-$mesiace months", $teraz ?? time()));
        return $this->vTransakcii(function () use ($hranica): array {
            $k = $this->pdo->prepare('DELETE FROM kontakty WHERE vytvorene < ?');
            $k->execute([$hranica]);
            $o = $this->pdo->prepare('DELETE FROM odhady WHERE vytvorene < ? AND id NOT IN (SELECT odhad_id FROM kontakty WHERE odhad_id IS NOT NULL)');
            $o->execute([$hranica]);
            return ['kontakty' => $k->rowCount(), 'odhady' => $o->rowCount()];
        });
    }

    private function vTransakcii(callable $f): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $r = $f();
            $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
