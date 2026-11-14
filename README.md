# nacenime.sk

Kalkulačka orientačnej ceny nehnuteľnosti pre **Liptov, Turiec a Oravu**. Majiteľ zadá adresu, odpovie na päť otázok a hneď uvidí cenové rozpätie. Kontakt sa pýta až na výsledku a je dobrovoľný. Prevádzkuje NajBývanie, s.r.o.

## Stav
- **Frontend** je hotový dizajnový prototyp (`design_handoff_nacenime/`). Zmeny sú povolené, ale v súlade so zadaním dizajnu (pozri `CLAUDE.md`).
- **Backend** (`backend/`) je hotový a otestovaný: výpočet odhadu, proxy pre Google Places, formuláre, SQLite, PDF a e-maily, GDPR nástroje.
- **Cookie lišta** (GTM a Clarity až po súhlase) je nasadená, **mapa zadarmo** (`frontend/mapa/`) je hotový modul a Google volania majú denný strop.
- **Nasadenie backendu na hosting** (bez SSH a Composera): návrh je v `deploy/NASADENIE-GIT.md`.
- **Napojenie frontendu na backend** ešte nie je spravené. Zmluva je v `docs/NAPOJENIE-FRONTENDU.md`.
- **Čaká na podklady:** cenové dáta pre tri okresy, Google kľúč, SMTP, texty a fotky. Pozri `docs/PODKLADY.md`.

## Štruktúra
| Cesta | Obsah |
|---|---|
| `design_handoff_nacenime/` | Dizajnový handoff: `README.md` (zadanie), HTML prototyp, cenník `data/nacenime-cennik.json`, JS referencia výpočtu `reference/` |
| `backend/` | PHP API (`src/`, `public/index.php`), CLI nástroje (`bin/`), testy (`tests/`). Detaily v `backend/README.md` |
| `docs/` | `openapi.yaml`, `NAPOJENIE-FRONTENDU.md`, `PODKLADY.md` |
| `deploy/` | Dockerfile, nginx, docker-compose (neoverené) |
| `.github/` | CI a zostavenie vetvy `deploy` |

## Rýchly štart
Potrebuješ PHP 8.1+ (`mbstring`, `pdo_sqlite`, `curl`, `dom`, `gd`), Composer a pre paritný test Node 20+.

```bash
composer install --working-dir=backend
cp backend/.env.example backend/.env
php backend/bin/skontroluj-konfig.php
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

Ukážkové volanie:

```bash
curl -s -X POST localhost:8080/api/odhad -H 'Content-Type: application/json' \
  -d '{"typ":"Byt","obec":"Ružomberok","stav":"Kompletná rekonštrukcia","vymera":66}'
```

## Testy
```bash
for t in run api pdf cennik prevadzka http subeznost fuzz; do php backend/tests/$t.php || break; done
backend/tests/parity.sh        # PHP vs. JS referencia
php backend/bin/kontrola-cennika.php
```
Všetky bežia aj v CI (`.github/workflows/ci.yml`).

## Dokumentácia
- Zadanie dizajnu a logiky: `design_handoff_nacenime/README.md`
- API: `docs/openapi.yaml`, `backend/README.md`
- Napojenie frontendu: `docs/NAPOJENIE-FRONTENDU.md`
- Čo treba doplniť: `docs/PODKLADY.md`
