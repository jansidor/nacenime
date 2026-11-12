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
        $this->pdo = new \PDO('sqlite:' . $cesta, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS odhady (
  id INTEGER PRIMARY KEY AUTOINCREMENT, vytvorene TEXT NOT NULL,
  adresa TEXT, psc TEXT, obec TEXT NOT NULL, lat REAL, lng REAL, bez_adresy INTEGER NOT NULL DEFAULT 0, zo_zalohy INTEGER NOT NULL DEFAULT 0,
  dovod TEXT, typ TEXT NOT NULL, vymera REAL NOT NULL, stav TEXT, balkon TEXT, predstava_ceny INTEGER,
  vysledok TEXT NOT NULL, cena_od INTEGER, cena_do INTEGER, pocet INTEGER
);
CREATE TABLE IF NOT EXISTS limity (kluc TEXT NOT NULL, okno INTEGER NOT NULL, pocet INTEGER NOT NULL, PRIMARY KEY (kluc, okno));
CREATE TABLE IF NOT EXISTS kontakty (
  id INTEGER PRIMARY KEY AUTOINCREMENT, vytvorene TEXT NOT NULL,
  druh TEXT NOT NULL CHECK (druh IN ('pdf','makler','mimo_regionu')),
  email TEXT, telefon TEXT, suhlas INTEGER NOT NULL DEFAULT 0, odhad_id INTEGER REFERENCES odhady(id),
  odoslane INTEGER NOT NULL DEFAULT 0
);
SQL);
    }

    /** @param array<string, mixed> $d */
    public function ulozOdhad(array $d): int
    {
        $s = $this->pdo->prepare('INSERT INTO odhady (vytvorene, adresa, psc, obec, lat, lng, bez_adresy, zo_zalohy, dovod, typ, vymera, stav, balkon, predstava_ceny, vysledok, cena_od, cena_do, pocet)
            VALUES (:vytvorene, :adresa, :psc, :obec, :lat, :lng, :bez_adresy, :zo_zalohy, :dovod, :typ, :vymera, :stav, :balkon, :predstava_ceny, :vysledok, :cena_od, :cena_do, :pocet)');
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

    public function odhadExistuje(int $id): bool
    {
        $s = $this->pdo->prepare('SELECT 1 FROM odhady WHERE id = ?');
        $s->execute([$id]);
        return (bool) $s->fetchColumn();
    }

    public function ulozKontakt(string $druh, ?string $email, ?string $telefon, bool $suhlas, ?int $odhadId): int
    {
        $s = $this->pdo->prepare('INSERT INTO kontakty (vytvorene, druh, email, telefon, suhlas, odhad_id) VALUES (?, ?, ?, ?, ?, ?)');
        $s->execute([gmdate('c'), $druh, $email, $telefon, (int) $suhlas, $odhadId]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function kontakty(string $druh): array
    {
        $s = $this->pdo->prepare('SELECT * FROM kontakty WHERE druh = ? ORDER BY id');
        $s->execute([$druh]);
        return $s->fetchAll(\PDO::FETCH_ASSOC);
    }
}
