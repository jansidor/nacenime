# Nasadenie na Hostcreators (zdieľaný hosting, PHP 8.1)

Zistené: doména `nacenime.sk` smeruje na `s24.hostcreators.sk` (193.163.77.230), server je nginx, PHP 8.1.
Pred prvým krokom treba od kolegu **prístup do hosting panelu** a vedieť, na ktorej (pod)doméne má API bežať.

## Čo je v balíku
```
app/    <- aplikácia, NESMIE byť vo verejnom priečinku (src, vendor, cenník, bin, var)
www/    <- verejný priečinok: api.php a .htaccess
NAVOD.md
```

## Postup
1. **Vytvor balík** (na vývojárskom počítači): `deploy/zostav-balik.sh`. Výsledok je `dist/nacenime-balik-<dátum>.zip`.
2. **Nahraj ZIP** cez FTP alebo správcu súborov a rozbaľ ho v domovskom priečinku hostingu tak, aby `app/` bol **vedľa** verejného priečinka, nie v ňom:
   ```
   /home/<používateľ>/app/            <- sem celý app/
   /home/<používateľ>/<docroot>/      <- obsah www/ (api.php, .htaccess); docroot je priečinok domény
   ```
   Ak panel dovolí nahrávať len do verejného priečinka, zmeň v `api.php` cestu na správnu (`require __DIR__ . '/../app/public/index.php';`).
3. **V paneli nastav PHP 8.1** pre doménu a zapni rozšírenia `pdo_sqlite`, `mbstring`, `curl`, `dom`, `gd` (odporúčané aj `intl`).
4. **Zapni SSL certifikát** (Let's Encrypt, býva v paneli zadarmo) pre doménu. Bez HTTPS nesmú používatelia posielať telefón ani e-mail a Google kľúč s obmedzením domény by nefungoval. Dnes `https://nacenime.sk` zlyháva na certifikáte.
5. **Vytvor konfiguráciu:** v `app/` skopíruj `.env.example` na `.env`, doplň Google kľúč, SMTP a meno makléra a nastav práva `600`. Cesty ku cenníku a databáze sú už relatívne (`data/…`, `var/…`). Priečinok `app/var/` musí byť zapisovateľný.
6. **Over:**
   - `https://<doména>/api/health` má vrátiť `{"stav":"ok"}`.
   - Ak je k dispozícii SSH: `php8.1 app/bin/skontroluj-konfig.php`.
7. **Cron** (panel, naplánované úlohy), cestu k PHP CLI overiť v paneli (napr. `/usr/bin/php8.1`):
   ```
   * * * * *  /usr/bin/php8.1 /home/<používateľ>/app/bin/odosli-pdf.php
   0 3 * * *  /usr/bin/php8.1 /home/<používateľ>/app/bin/zaloha-db.php
   30 3 * * * /usr/bin/php8.1 /home/<používateľ>/app/bin/gdpr.php retencia
   ```

## Ak `.htaccess` nefunguje (čistý nginx)
Príznak: `/api/health` vráti 404 alebo 403, ale `/api.php` odpovedá. Vtedy požiadaj podporu hostingu, aby pre doménu pridala do nginx konfigurácie:
```
location /api/ { try_files $uri /api.php?$query_string; }
```
(alebo pravidlo `rewrite ^/api(/.*)?$ /api.php last;`).

## Čo overiť u poskytovateľa (otázky pre podporu)
- Je dostupný SSH a CLI PHP 8.1 (pre cron a kontrolu)?
- Dá sa zapnúť `pdo_sqlite` a kam smie PHP zapisovať?
- Je povolené odchádzajúce spojenie na `places.googleapis.com` a na SMTP (niektoré hostingy blokujú porty)?
- Obmedzenia na cron (najkratší interval) a veľkosť nahrávaných súborov.

## Bezpečnosť
- `.env`, cenník, databáza a zálohy musia byť **mimo** verejného priečinka. Balík ich tak ukladá.
- Po nasadení over, že `https://<doména>/app/` ani `https://<doména>/.env` nie sú dostupné (majú vrátiť 404 alebo 403).
- Do balíka sa nedostane `.env` ani databáza, ani testy a dev závislosti.
