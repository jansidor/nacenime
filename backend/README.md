# Backend

PHP API pre nacenime.sk. Frontend (`design_handoff_nacenime/`) je zmrazený a backend sa naň len napája.
Bez Composeru a bez knižníc. Treba PHP 8.1+ s `mbstring`, `pdo_sqlite` a `curl`.

## Spustenie lokálne
```bash
cp backend/.env.example backend/.env      # a doplň hodnoty
composer install --working-dir=backend   # dompdf, PHPMailer
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

## Endpointy
Všetky odpovede sú JSON. `POST` vyžaduje `Content-Type: application/json` (inak 415). Chyby validácie: `422 {"chyby": {pole: "veta"}}`.

| Endpoint | Telo / parametre | Odpoveď |
|---|---|---|
| `POST /api/odhad` | `typ`, `obec`, `stav` (pri pozemku netreba), `vymera`; voliteľne `dovod`, `balkon`, `predstava_ceny`, `adresa {ulica, psc, lat, lng, zo_zalohy, bez_adresy}` | výsledok odhadu (`stav`: `ok`, `málo dát`, `mimo regiónu`, `maklér`), `odhad_id`, `makler` |
| `GET /api/adresa?q=&token=` | od 3 znakov, `token` = session token | `{navrhy: [{id, hlavny, vedlajsi}]}` alebo `{zaloha: true}` |
| `GET /api/adresa/detail?id=&token=` | `id` z návrhu | `{ulica, psc, obec, lat, lng, v_regione}` alebo `{zaloha: true}` |
| `GET /api/health` | | `{stav: ok}` (200) alebo `{stav: chyba}` (503), mimo limitu |
| `GET /api/obec?obec=` | záloha bez Google | `{v_regione, obec, okres}` |
| `POST /api/kontakt/pdf` | `email`, `suhlas` (len `true`), `odhad_id` | `{sprava}` |
| `POST /api/kontakt/makler` | `telefon` (min. 9 číslic), `odhad_id` | `{sprava}` |
| `POST /api/kontakt/mimo-regionu` | `email` | `{sprava}` |

Keď `zaloha: true`, frontend zobrazí polia „Ulica a číslo“ a „Obec“ a obec overí cez `/api/obec`.
Formuláre majú honeypot: skryté pole `web` musí zostať prázdne.

## PDF a e-maily
`POST /api/kontakt/pdf` kontakt len uloží. E-maily s PDF odosiela `php backend/bin/odosli-pdf.php`, ktorý treba pustiť z cronu (napr. každú minútu).
Zlyhanie sa opakuje najviac 5-krát, odoslané sa neposielajú znova. SMTP sa nastavuje v `.env` (`NACENIME_SMTP_*`), heslo nikdy v kóde.
Skutočné odoslanie cez SMTP som neskúšal (nemám údaje), testované je s falošným odosielateľom.

## Bezpečnosť
- Google API kľúč je len v `.env`, nikdy v odpovediach ani v logoch.
- Limit `NACENIME_RATE_LIMIT` požiadaviek za minútu na IP (predvolene 60). Za proxy nastav `NACENIME_TRUSTED_PROXY=1`.
- Cenník a databázu (`var/`) drž mimo verejného priečinka. Webserver má servírovať len `backend/public/`.
- Chyby idú len do logu, klient dostane všeobecnú správu.

## Nástroje (CLI)
- Leady pre makléra: `php backend/bin/leady.php [YYYY-MM-DD] > leady.csv` (telefonáty a PDF so súhlasom; vzorce v bunkách sú neutralizované).
- GDPR: `php backend/bin/gdpr.php export|zmaz|retencia`. Retenciu pusti z cronu (napr. denne).
- Konfigurácia: `php backend/bin/skontroluj-konfig.php` (pred nasadením a po zmene `.env`; tajomstvá nevypisuje).
- Záloha DB: `php backend/bin/zaloha-db.php [priečinok] [ponechať=14]` (konzistentná kópia, práva 0600, rotácia). Cron denne.
- Cenník: `php backend/bin/kontrola-cennika.php` (kontrola) a `php backend/bin/zostav-cennik.php vstup.csv [--zapis]` (zostavenie zo surových predajov a ponúk, stĺpce `zdroj,typ,obec,stav,cena,plocha`). Metóda kvartilov je lineárna interpolácia, **predpoklad na potvrdenie**.

## Nasadenie
Pozri `deploy/`: `Dockerfile` (php-fpm), `nginx.conf` (servíruje len `backend/public`, zakazuje `.env`, `var`, `vendor`) a `docker-compose.yml`.
**Neoverené:** Dockerfile a nginx konfiguráciu som nemohol zostaviť ani spustiť (v mojom prostredí nebeží Docker), pred nasadením ich otestuj. Prototyp frontendu číta `data/nacenime-cennik.json` z prehliadača, preto `nginx.conf` blokuje `.json`. V produkcii má byť cenník len na serveri.
`/api/health` slúži na monitoring. Cron: `odosli-pdf.php` každú minútu, `gdpr.php retencia` denne.

## Testy
```bash
php backend/bin/kontrola-cennika.php   # konzistencia cenníka
php backend/tests/run.php              # výpočet odhadu
php backend/tests/api.php              # endpointy, validácia, limity, Google (s falošným klientom)
php backend/tests/pdf.php              # PDF a odosielanie e-mailov (s falošným odosielateľom)
php backend/tests/prevadzka.php        # kontrola konfigurácie a záloha DB
php backend/tests/cennik.php           # zostavenie cenníka a formát JSON
php backend/tests/http.php             # skutočný HTTP server: kódy, hlavičky, limity veľkosti
php backend/tests/fuzz.php [seed]      # náhodné a škodlivé vstupy: žiadna výnimka, varovanie ani 5xx
backend/tests/parity.sh                # PHP vs. JS referencia na všetkých kombináciách
```
Volania na skutočné Google API sa v testoch nespúšťajú (treba kľúč). Overené sú len s falošným klientom.

## Známe obmedzenia
- Filter okresov pre návrhy je obdĺžnik okolo regiónu. Presná kontrola je podľa obce v Place Details (`v_regione`).
- Zoznam obcí je úplný (213 + `Dovalovo` z pôvodných dát), ale cenník nemá cenové dáta pre okresy Tvrdošín, Turčianske Teplice a Námestovo, takže tam výsledok býva „málo dát“ (`bin/kontrola-cennika.php` na to upozorní).
