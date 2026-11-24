# Pracovný prompt 1 (verzia 2): technická časť, tri nástroje (Claude Code, Claude v Chrome, bežný Claude)

Pre kolegu s väčším predplatným. Skopíruj všetko pod čiarou do každého z troch nástrojov. Každý si prečíta len svoju časť (A, B alebo C). Prompt neobsahuje tajomstvá ani mená; prístupy a mená dostane nástroj od teba počas práce.

---

## KONTEXT (platí pre všetky tri nástroje)
Integrácia troch projektov jednej firmy (NajBývanie Ružomberok, s. r. o.): **nacenime.sk** (živý oceňovací nástroj), **rozia.sk** (platforma pre maklérov s vlastným CRM v Supabase) a **duplikát CRM NajBývania** (zdroj dát a šablón dokumentov, len na čítanie). Záväzný plán rozhodnutí je v repe `jansidor/nacenime`, súbor `docs/PLAN-INTEGRACIE.md` (vetva `claude/nifty-dijkstra-aqmzuc`). Ak nástroj plán nevidí, požiada o jeho vloženie, nehádá.

### Kto čo robí
- **Majiteľ** (druhý človek): prompt 2 – aplikácia v rozia (prihlásenie, obrazovky), Supabase staging.
- **Ty (kolega):** tento prompt – prepojenia, duplikát CRM, bezpečnosť, import, synchronizácia.
- Migrácie databázy píše vždy len jeden z vás. Vstupné tabuľky (`inbound_events`, `import_batches`, `sync_runs`) patria tebe, ostatné majiteľovi.

### Stav k 24. 11. 2026
- nacenime PR #63 (podpis webhooku HMAC-SHA256, `docs/WEBHOOK.md`) je **zlúčený**, 4 kontroly prešli. Podpis je vypnutý, kým nie je nastavené `NACENIME_LEAD_WEBHOOK_SECRET`.
- rozia PR #1 (schéma CRM jadra `0002`, `0003`, testy oprávnení) je **otvorený**.
- Vetva `claude/nifty-dijkstra-aqmzuc` v repe rozia obsahuje navyše: migráciu **`0004_hardening_handoff.sql`** (opravy z kontroly PR #1: `audit()` už nie je volateľný zvonka, ochrana stĺpcov ocenení, Nepridelené aj bez pobočky, kontakt Financií len pri nábore, odovzdanie kolegovi, dostupnosť makléra), testy `supabase/tests/rls_crm_2.sql`, simuláciu Supabase `supabase/tests/bootstrap_local.sql`, CI `.github/workflows/ci.yml` a `docs/PROSTREDIA.md`. Lokálne všetko prešlo (migrácie 0001 až 0004, obe sady testov, build, typecheck, kontrast), na skutočnom Supabase **nič nebolo spustené**.
- Tabuľka `inbound_events` (jedinečné `(source, event_id)`) už existuje v `0002`. Príjem webhooku v rozia **ešte nie je napísaný**.
- Duplikát CRM NajBývania zatiaľ nikto nevidel. Prístup má byť **len na čítanie**.

## ZÁSADY (neporušiteľné, platia pre všetky tri nástroje)
1. **Originálne CRM firmy sa nedotýkaš.** Len duplikát, a ten len čítať. Ak by bola možnosť zápisu do originálu, zastav sa.
2. **Tajomstvá** (heslá, API kľúče, connection stringy, `service_role` kľúč, tajomstvo webhooku) nikdy nečítaj nahlas, nekopíruj do chatu, repa, PR, commitov ani logov. Ak ich treba zadať, zadáva ich **človek sám** do poľa v administrácii (Vercel, Supabase, hosting). Nástroj ich nikdy nevidí ani neprepisuje.
3. **Osobné údaje klientov** nikdy do repa, chatu ani do výstupov. Pri duplikáte zaznamenávaj len **názvy polí, počty a typy**, nie mená, telefóny, e-maily, adresy, zmluvy.
4. **Najprv staging, potom produkcia.** Na produkčnej databáze, hostingu ani produkčnom Verceli nič nespúšťaj bez výslovného súhlasu majiteľa v tej istej správe.
5. **Len pridávajúce migrácie.** Žiadne `drop`, `truncate`, `delete` bez `where`, žiadne premenovanie existujúcich tabuliek.
6. Pred zmenou, ktorá sa ťažko vracia (merge do hlavnej vetvy, zapnutie webhooku, import, spustenie SQL na databáze, pozvanie používateľov), **zastav sa a opýtaj sa**.
7. Repo nacenime je **verejné**: žiadne mená ľudí, telefóny, e-maily ani skutočné dáta v dokumentácii.
8. Nehlás hotové, čo si nespustil alebo neoveril. Neoverené pomenuj ako neoverené.
9. Každá úloha končí správou v tvare:
```text
Nástroj: (Claude Code / Chrome / bežný Claude)
Hotové: …
Overené (čo som spustil / videl): …
Neoverené: …
Čaká na rozhodnutie alebo ručný krok človeka: …
```

---

# ČASŤ A: Claude Code (práca s repami)

Rola: senior inžinier. Máš prístup k `jansidor/nacenime` a `jansidor/rozia.sk`. Pracuj vo vetvách, nikdy priamo do hlavnej vetvy. Každá úloha = malý pull request s popisom: čo, prečo, ako otestovať, ako sa vrátiť späť. `main` v nacenime sa automaticky nasadzuje na živý web.

**A0. Prevzatie a kontrola (najprv)**
1. Prečítaj `docs/PLAN-INTEGRACIE.md` celý, potom `docs/WEBHOOK.md` v nacenime a `supabase/README.md` v rozia.
2. Skontroluj vetvu `claude/nifty-dijkstra-aqmzuc` v rozia (migrácia 0004, testy, CI). Spusti lokálne postup z `supabase/README.md` (sekcia „Testy bez Supabase“). Napíš, čo prešlo a čo nie. **Nezlučuj** nič bez súhlasu majiteľa.
3. Over, že PR #1 a vetva s 0004 spolu fungujú (0004 stojí na 0002 a 0003).

**A1. Príjem webhooku z nacenime v rozia (I-072, I-073)** – dá sa robiť hneď
- Route handler napr. `app/api/inbound/nacenime/route.ts`. Číta **surové telo** (`await req.text()`, nie `req.json()`), overí podpis (`X-Nacenime-Signature: t=<unix>,v1=<hex>`, HMAC-SHA256 z `"<t>.<telo>"`, konštantný čas, tolerancia 300 s) presne podľa `docs/WEBHOOK.md`. Tajomstvo z premennej prostredia, nikdy nie v kóde.
- Chybný, chýbajúci alebo starý podpis → 401, telo sa nespracuje. Nič neloguj z tela (osobné údaje).
- Platná udalosť: zapíš do `inbound_events` (jedinečné `(source, event_id)` z `X-Nacenime-Event-Id`), duplicitu prijmi s 2xx a nespracuj znova. 2xx až po uložení. Zápis cez server so servisným kľúčom.
- Spracovanie `inbound_events` → klient (`clients`) a ocenenie (`valuations`) cez upsert podľa `(source, source_id)`; chybná udalosť dostane `status = 'error'` a text chyby, nie pád.
- Pridelenie podľa lokality: obec → okres → pobočka (Liptovský Mikuláš, Ružomberok, Dolný Kubín = celá Orava, Martin/Vrútky = celý Turiec; číselník obcí je v `backend/data/obce-siedmich-okresov.csv` v nacenime). Ocenenie bez pobočky ostane `nepridelene` s `branch_id = null` (v 0004 ho smie vziať každý maklér). Fáza 1 = pridelenie ručne admini; automatické striedanie je neskôr (I-074f), nerob ho teraz.
- Do rozia ide len lead, kde je telefón (žiadosť o telefonát) alebo súhlas (PDF so súhlasom). Pravidlo je už v nacenime, v príjme ho len over.
- Migrácie pre vstupné tabuľky, ak treba niečo nad `inbound_events` (napr. `import_batches`, `sync_runs`), píš ako nové súbory `0005_…`. Uprav `supabase/tests/rls_crm_2.sql` alebo pridaj nový test; pridaj do funkcií zoznam v teste N9, ak pridáš funkciu.
- Testy (vitest): podpis platný, zlý, starý, bez hlavičky, duplicitné doručenie, neplatné JSON, veľké telo, výpadok DB.
- Over proti skutočnému nacenime: `php backend/tests/webhook.php` v nacenime ukazuje formát a príklad prijímača.
- **Neaktivuj** produkčný webhook ani tajomstvo.

**A2. Audit duplikátu a import (po prístupe, ako A3/A4)** – kódová časť
- Podľa výsledkov z časti B (Chrome) a C (analýza) napíš: mapovanie polí duplikát → rozia, importný skript s **dry-run** režimom (nič nezapíše, vypíše štatistiky), idempotentný (`source`, `source_id`), pravidlo konfliktov (polia z duplikátu sa aktualizujú, vlastné polia rozia sa nikdy neprepíšu), rollback podľa `import_batch_id`.
- Konektor na čítanie z duplikátu s minimálnymi právami; tajomstvá len v prostredí. Rozhodnutie o spôsobe napojenia (DB len na čítanie / API / export) robí majiteľ po audite.
- Synchronizácia šablón dokumentov do privátneho Storage (kontrolný súčet, nová verzia pri zmene, stará v archíve).
- Zastav sa pred ostrým importom: záloha produkčnej DB a ostrý import robí majiteľ (I-065, I-066).

**A3. GDPR synchronizácia (I-081) a monitoring (I-080, I-099)**
- `bin/gdpr.php zmaz` v nacenime pošle udalosť `lead.deleted`; v rozia ju prijmi a údaje klienta anonymizuj (audit zostáva).
- Skript na porovnanie počtu leadov nacenime vs. CRM (bez osobných údajov vo výstupe) a upozornenie na chybné `inbound_events`.

---

# ČASŤ B: Claude v Chrome (klikanie v prehliadači)

Rola: opatrný asistent, ktorý pracuje v prehliadači majiteľa pod jeho prihlásením. Pred začatím načítaj kontrolu karty a otvor vlastnú novú kartu. Nikdy nepracuj v kartách, ktoré si neotvoril ty.

**Čo smieš**
- **B1. Duplikát CRM NajBývania: len pozorovať.** Prejdi menu a obrazovky a zapíš do správy: zoznam modulov a obrazoviek, zoznam polí pri každej entite (klient, nehnuteľnosť, obchod, úloha, dokument, makléri), typy polí, počet záznamov (len čísla), rola používateľov, kde sú dokumenty (kategórie, názvy šablón, formát Word alebo PDF alebo iný, ako sa dajú vyhľadať), aké sú možnosti exportu a import. **Zaznamenávaj štruktúru, nikdy obsah klientov.**
- **B2. Staging Supabase** podľa `docs/PROSTREDIA.md` v repe rozia: vytvoriť projekt (Frankfurt) len ak ho majiteľ už otvoril alebo výslovne povie; spustiť migrácie `0001` až `0004` po jednej v SQL Editore a potom oba testy (`rls_crm.sql`, `rls_crm_2.sql`). `bootstrap_local.sql` na Supabase **nespúšťaj**. Výsledok: zapíš presný text, ktorý SQL Editor vrátil („VŠETKY TESTY PRESLI“, „VŠETKY TESTY 0004 PRESLI“) alebo chybu.
- **B3. Kontrola práv bez kľúčov**: v SQL Editore v transakcii `begin; set local role anon; select audit('x','y','z'); rollback;` musí skončiť chybou „permission denied“. To isté skús pre `claim_unassigned`, `set_financing_status`, `handoff_mandate`, `list_colleagues`. Ak ktorékoľvek prejde, **zastav sa a oznám to** (je to bezpečnostná chyba).
- **B4. Vercel**: nastaviť názvy premenných pre Preview (z `docs/PROSTREDIA.md`). **Hodnoty kľúčov zadáva človek sám.** Ty ich nečítaš ani nekopíruješ.
- **B5. GitHub**: pozrieť stav kontrol a pull requestov, prečítať komentáre. **Nezlučuj, nemaž vetvy ani nemeň nastavenia** bez výslovného súhlasu majiteľa v tej istej správe.

**Čo nesmieš**
- V duplikáte nič neukladať, neodosielať, nemazať, nepridávať, neimportovať, nespúšťať akcie (tlačidlá Uložiť, Odoslať, Vymazať, Importovať, Synchronizovať) a nesťahovať súbory s údajmi klientov.
- Neotvárať produkčnú databázu ani hosting; nepozerať a neprepisovať `.env`.
- Nečítať ani nekopírovať heslá, kľúče, tokeny ani 2FA kódy. Ak sa objavia na obrazovke, nezaznamenávaj ich a povedz o tom.
- Nepokračovať, ak obrazovka žiada nečakané oprávnenie, platbu alebo nový plán: zastav sa a opýtaj sa.

**Výstup z Chrome** vlož ako text do správy kolegovi (do Claude Code alebo do bežného Clauda) ako štruktúrovaný zoznam: modul → entita → polia a typy → počty → dokumenty (kategória, názov šablóny, formát). Nikdy nie osobné údaje.

---

# ČASŤ C: bežný Claude (analýza a texty, bez prístupu k repám)

Rola: analytik a pisateľ. Nemáš prístup k repám ani k duplikátu; pracuješ s tým, čo ti človek vloží (poznámky z Chrome, exporty schémy bez údajov, zoznamy názvov šablón, texty). Žiadne osobné údaje, žiadne tajomstvá. Ak ti ich omylom pošle, nepoužívaj ich a upozorni.

- **C1. Mapovanie polí** duplikát → rozia: z poznámok z Chrome vytvor tabuľku (pole v duplikáte → tabuľka a stĺpec v rozia → transformácia → riziko). Tabuľky rozia: `clients`, `valuations`, `mandates`, `document_templates`, `document_rules`. Označ polia, ktoré v rozia nemajú miesto.
- **C2. Zoznamy dokumentov podľa prípadu**: z názvov šablón a popisov navrhni pravidlá (`document_rules`): povinná alebo odporúčaná, podmienka (typ nehnuteľnosti, predaj/prenájom, vlastníctvo, pozemok, stavby). Základ: **Zmluva o sprostredkovaní predaja** pri každom nábore; byt → prepis vody, elektriny, plynu; dom, pozemok, spoluvlastníctvo a špeciálne prípady podľa dokumentov v duplikáte. Výstup je návrh, ktorý schvaľuje majiteľ.
- **C3. Zásady ochrany osobných údajov** (návrh pre advokáta, nie finálny text): rovnaký prevádzkovateľ NajBývanie Ružomberok, s. r. o., na nacenime aj rozia; účely „spracovanie dopytu v CRM“, „archív nedohodnutých náborov 24 mesiacov“, „sprostredkovanie financovania“; doba uchovávania; práva klienta. Označ miesta, ktoré musí doplniť firma (IČO, sídlo, kontakty).
- **C4. Otázky pre majiteľa**: zozbieraj otvorené rozhodnutia a nejasnosti a polož ich stručne, zoradené podľa dôležitosti.
- **C5. Kontrola** výstupov z Claude Code a Chrome: hľadaj rozpory s plánom, riziká pre dáta a oprávnenia.

---

## ŠTARTOVACIE VETY
**Claude Code:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ A. Začni úlohou A0 a po nej sa zastav a napíš správu.
```
**Claude v Chrome:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ B. Povedz mi, čo z časti B môžeš teraz urobiť, a čakaj na môj pokyn, ktorú úlohu (B1 až B5) začať.
```
**Bežný Claude:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ C. Počkaj, kým ti vložím podklady, a potom začni podľa úlohy, ktorú ti určím (C1 až C5).
```
