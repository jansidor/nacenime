# Backend

PHP API pre nacenime.sk. Frontend (`design_handoff_nacenime/`) je zmrazený a backend sa naň len napája.
Bez Composeru a bez knižníc. Treba PHP 8.1+ s `mbstring`, `pdo_sqlite` a `curl`.

## Spustenie lokálne
```bash
cp backend/.env.example backend/.env      # a doplň hodnoty
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

## Endpointy
Všetky odpovede sú JSON. `POST` vyžaduje `Content-Type: application/json` (inak 415). Chyby validácie: `422 {"chyby": {pole: "veta"}}`.

| Endpoint | Telo / parametre | Odpoveď |
|---|---|---|
| `POST /api/odhad` | `typ`, `obec`, `stav` (pri pozemku netreba), `vymera`; voliteľne `dovod`, `balkon`, `predstava_ceny`, `adresa {ulica, psc, lat, lng, zo_zalohy, bez_adresy}` | výsledok odhadu (`stav`: `ok`, `málo dát`, `mimo regiónu`, `maklér`), `odhad_id`, `makler` |
| `GET /api/adresa?q=&token=` | od 3 znakov, `token` = session token | `{navrhy: [{id, hlavny, vedlajsi}]}` alebo `{zaloha: true}` |
| `GET /api/adresa/detail?id=&token=` | `id` z návrhu | `{ulica, psc, obec, lat, lng, v_regione}` alebo `{zaloha: true}` |
| `GET /api/obec?obec=` | záloha bez Google | `{v_regione, obec, okres}` |
| `POST /api/kontakt/pdf` | `email`, `suhlas` (len `true`), `odhad_id` | `{sprava}` |
| `POST /api/kontakt/makler` | `telefon` (min. 9 číslic), `odhad_id` | `{sprava}` |
| `POST /api/kontakt/mimo-regionu` | `email` | `{sprava}` |

Keď `zaloha: true`, frontend zobrazí polia „Ulica a číslo“ a „Obec“ a obec overí cez `/api/obec`.
Formuláre majú honeypot: skryté pole `web` musí zostať prázdne.

## Bezpečnosť
- Google API kľúč je len v `.env`, nikdy v odpovediach ani v logoch.
- Limit `NACENIME_RATE_LIMIT` požiadaviek za minútu na IP (predvolene 60). Za proxy nastav `NACENIME_TRUSTED_PROXY=1`.
- Cenník a databázu (`var/`) drž mimo verejného priečinka. Webserver má servírovať len `backend/public/`.
- Chyby idú len do logu, klient dostane všeobecnú správu.

## Testy
```bash
php backend/bin/kontrola-cennika.php   # konzistencia cenníka
php backend/tests/run.php              # výpočet odhadu
php backend/tests/api.php              # endpointy, validácia, limity, Google (s falošným klientom)
backend/tests/parity.sh                # PHP vs. JS referencia na všetkých kombináciách
```
Volania na skutočné Google API sa v testoch nespúšťajú (treba kľúč). Overené sú len s falošným klientom.

## Známe obmedzenia
- Filter okresov pre návrhy je obdĺžnik okolo regiónu. Presná kontrola je podľa obce v Place Details (`v_regione`).
- GDPR: `php backend/bin/gdpr.php export|zmaz|retencia`. Retenciu pusti z cronu (napr. denne).
- PDF a e-maily sa zatiaľ len ukladajú do tabuľky `kontakty` (stĺpec `odoslane`).
- Cenník nemá úplné dáta pre okresy Tvrdošín, Turčianske Teplice a Námestovo (`bin/kontrola-cennika.php` na to upozorní).
