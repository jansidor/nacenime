# nacenime.sk

Kalkulačka orientačnej ceny nehnuteľnosti pre **Liptov, Turiec a Oravu**. Majiteľ zadá adresu, odpovie na päť otázok a hneď uvidí cenové rozpätie. Kontakt sa pýta až na výsledku a je dobrovoľný. Prevádzkuje NajBývanie Ružomberok, s. r. o.

## Stav (15. 11. 2026)
- **Živý web** `https://nacenime.sk`: kalkulačka je napojená na backend (našepkávač adries cez Google, mapa OpenStreetMap, odhad, formuláre PDF a maklér), cookie lišta, právne stránky, Search Console a sitemap.
- **Backend** (`backend/`): výpočet odhadu, proxy pre Google Places s denným stropom, formuláre, SQLite, PDF a e-maily, oznámenia o leadoch, GDPR nástroje.
- **CRM** (`crm/`, `crm.nacenime.sk`): fáza 1, leady z kalkulačky, role Správca a Maklér. Nastavenie na hostingu v `deploy/NASADENIE-GIT.md`.
- **Nasadenie:** `main` sa automaticky nasadí cez vetvu `deploy` (s knižnicami), `.env` a databáza sú v `shared/` mimo releaseov.
- **Čaká:** cenové dáta pre Tvrdošín, Turčianske Teplice a Námestovo (`docs/CENNIK-PODKLADY.md`), fotky a texty miest, nastavenie SMTP, CRM subdomény, GA4 a e-mailových DNS záznamov. Pozri `docs/KONTROLNY-ZOZNAM-SPUSTENIA.md`.

## Štruktúra
| Cesta | Obsah |
|---|---|
| `design_handoff_nacenime/` | Koreň webu: kalkulačka `Nacenime.dc.html`, zadanie dizajnu `README.md`, cookie lišta `cookies/`, mapa `mapa/`, lokálny runtime `vendor/`, právne stránky, cenník `data/` (na webe skrytý), JS referencia `reference/` |
| `crm/` | CRM pre maklérov (`crm.nacenime.sk`) |
| `frontend/napojenie/` | Test celého toku kalkulačky v prehliadači proti PHP backendu |
| `backend/` | PHP API (`src/`, `public/index.php`), CLI nástroje (`bin/`), testy (`tests/`). Detaily v `backend/README.md` |
| `docs/` | `openapi.yaml`, `NAPOJENIE-FRONTENDU.md`, `PODKLADY.md`, `CENNIK-PODKLADY.md`, `KONTROLNY-ZOZNAM-SPUSTENIA.md` |
| `deploy/` | Nasadenie z Gitu na Hostcreators (`NASADENIE-GIT.md`, `hosting/`, `zostav-deploy-strom.sh`); Dockerfile a nginx (neoverené) |
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
for t in run api pdf oznamenia crm cennik prevadzka http subeznost fuzz nasadenie; do php backend/tests/$t.php || break; done
backend/tests/parity.sh        # PHP vs. JS referencia
php backend/bin/kontrola-cennika.php
node design_handoff_nacenime/cookies/test.cjs   # v prehliadači (Playwright)
node design_handoff_nacenime/mapa/test.cjs
node frontend/napojenie/test.cjs
```
Bežia aj v CI (`.github/workflows/ci.yml`).

## Dokumentácia
- Zadanie dizajnu a logiky: `design_handoff_nacenime/README.md`
- API: `docs/openapi.yaml`, `backend/README.md`
- Napojenie frontendu: `docs/NAPOJENIE-FRONTENDU.md`
- Čo treba doplniť: `docs/PODKLADY.md`, cenové dáta: `docs/CENNIK-PODKLADY.md`
- Cenník: odkiaľ sú čísla a vzorce rozpätia: `docs/CENNIK-VZORCE.md`
- Stav spustenia: `docs/KONTROLNY-ZOZNAM-SPUSTENIA.md`
