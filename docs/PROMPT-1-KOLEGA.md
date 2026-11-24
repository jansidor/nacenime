# Pracovný prompt 1 (verzia 3, 25. 11. 2026): kolega – duplikát CRM, kontrola kódu, skúška nasadenia

Pre kolegu. Skopíruj všetko pod čiarou do každého z troch nástrojov (Claude Code, Claude v Chrome, bežný Claude). Každý číta len svoju časť (A, B alebo C). Prompt neobsahuje tajomstvá ani mená; prístupy a mená dostane nástroj od teba počas práce.

---

## KONTEXT (platí pre všetky tri nástroje)
Integrácia troch projektov jednej firmy (NajBývanie Ružomberok, s. r. o.): **nacenime.sk** (živý oceňovací nástroj), **rozia.sk** (platforma pre maklérov s vlastným CRM v Supabase) a **duplikát CRM NajBývania** (zdroj dát a šablón dokumentov, **len na čítanie**). Záväzný plán rozhodnutí je v repe `jansidor/nacenime`, súbor `docs/PLAN-INTEGRACIE.md`. Ak nástroj plán nevidí, požiada o jeho vloženie, nehádá.

### Stav k 24. 11. 2026 (večer)
- **Hotové a zlúčené:** podpis webhooku v nacenime; v rozia migrácie `0001` až `0008`, testy oprávnení (6 sád) a CI (zelené); príjem leadov z nacenime (route `/api/inbound/nacenime/` + databázová funkcia); uchovávanie údajov a denná údržba (`/api/cron/retention/`); **CRM aplikácia v1** (`/crm/`: prihlásenie, ocenenia, Nepridelené, pridelenie adminom, nábory s detailom, vrátený klient, archív, odovzdanie kolegovi, financovanie); **zásady ochrany údajov schválené a zverejnené** na nacenime.sk aj v rozia.
- **Overené na skutočnom testovacom Supabase:** migrácie 0001 až 0008, všetky testy, verejný kľúč nevolá funkcie CRM. **Neoverené v prehliadači:** prihlásenie a obrazovky CRM (zatiaľ nie je nasadené).
- **Nenasadené:** doména `rozia.sk` v DNS neexistuje a repo rozia pravdepodobne nie je napojené na Vercel. Rieši to majiteľ (jeho zoznam úloh je v `docs/ULOHY-MAJITEL.md`).
- **Webhook z nacenime do CRM je vypnutý** a zapína ho až majiteľ.
- **Duplikát CRM NajBývania:** prístup len na čítanie mal prísť v pondelok; ak ho ešte nemáš, povedz to majiteľovi hneď ráno. Tvoja hlavná práca čaká práve na neho.
- Repo rozia nemá vetvu `main`; hlavná vetva je `claude/eager-edison-tqydun`. Pracuj vo vlastnej vetve a rob pull requesty.

### Kto čo robí
- **Majiteľ:** doména, Vercel, premenné prostredia, vyskúšanie obrazoviek CRM, zapnutie webhooku, skutoční zamestnanci.
- **Ty (kolega):** duplikát CRM (audit, mapovanie, import, šablóny), nezávislá kontrola kódu a bezpečnosti, skúška nasadenia po tom, čo ho majiteľ pripraví.
- Migrácie databázy píše vždy len jeden z vás. Ak potrebuješ novú tabuľku alebo stĺpec, napíš to majiteľovi alebo urob pull request s migráciou `0009_…` a názvom, ktorý ešte nikto nepoužil.

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

**A0. Prevzatie a nezávislá kontrola (hneď, nepotrebuje duplikát)**
1. Prečítaj `docs/PLAN-INTEGRACIE.md`, `docs/WEBHOOK.md` (nacenime) a `supabase/README.md`, `docs/PROSTREDIA.md` (rozia).
2. Spusti lokálne postup „Testy bez Supabase“ z `supabase/README.md` a `npm test`, `npm run typecheck`, `npm run build`. Napíš, čo prešlo.
3. **Kontrola kódu CRM a webhooku** (nová aplikácia nebola nikdy skúšaná v prehliadači). Prejdi najmä: `app/crm/actions.ts`, `lib/crm/*`, `proxy.ts`, `app/api/inbound/nacenime/route.ts`, `lib/inbound.ts`, `lib/webhook.ts`, `app/api/cron/retention/route.ts` a migrácie `supabase/migrations/0004` až `0008`. Hľadaj: obídenie oprávnení, chýbajúcu validáciu vstupov, únik osobných údajov do chýb alebo logov, otvorené presmerovania, chyby v RLS, nesprávne dotazy (PostgREST vnorené výbery), problémy s cookies relácie. Výstup: zoznam nálezov s vážnosťou a návrhom opravy. **Nálezy nezlučuj sám**: malé opravy daj do pull requestu a popíš ich.
4. Skontroluj, že CI v oboch repách je zelené a že testy naozaj niečo overujú (nie sú prázdne).

**A1. Skúška nasadenia (až keď majiteľ pripraví Vercel a doménu alebo `*.vercel.app`)**
- Pomôž overiť príjem leadov: `NACENIME_WEBHOOK_SECRET=… node scripts/send-test-lead.mjs https://<adresa>/api/inbound/nacenime/` (adresa s lomkou na konci). Očakávané `HTTP 200` a `{"status":"processed"}`; opakovanie s rovnakým id `duplicate`; zlý podpis `401`. Tajomstvo nikdy nevypisuj do chatu.
- Over denné čistenie: route `/api/cron/retention/` s hlavičkou `Authorization: Bearer <CRON_SECRET>` (bez neho 401).
- **Neaktivuj** produkčný webhook v nacenime. Zapnutie robí majiteľ po vašej spoločnej skúške.

**A2. Duplikát CRM NajBývania (po získaní prístupu len na čítanie)**
1. Over, že duplikát je oddelený od originálu (iná databáza, iné prihlasovacie údaje, žiadna replikácia späť). Ak by existovala čo i len možnosť zápisu do originálu, zastav sa.
2. Zisti technológiu, schému (tabuľky, vzťahy, počty riadkov) a entity: klienti, nehnuteľnosti, obchody, makléri, úlohy, dokumenty, prílohy. Výstup do `docs/duplikat/` (ER diagram a slovník polí, **bez osobných údajov**).
3. Používatelia a role v duplikáte, umiestnenie súborov a ich objem, kvalita dát (duplicity, formáty telefónov, kódovanie diakritiky).
4. **Dokumenty a šablóny:** kde sú, formát (očakávame prevažne Word, niektoré PDF), kategórie, verzie, či majú vyznačené polia na vyplnenie.
5. Bezpečnostný audit a licencia kódu duplikátu.
6. Správa s odporúčaním **spôsobu napojenia** (databáza len na čítanie / API / export) a frekvencie synchronizácie. **Zastav sa a nechaj majiteľa rozhodnúť**, kým nepôjdeš ďalej.

**A3. Import a synchronizácia (po rozhodnutí o napojení)**
- Mapovanie polí duplikát → rozia, importný skript s **dry-run** režimom (nič nezapíše, vypíše štatistiky), idempotentný podľa (`source`, `source_id`), pravidlo konfliktov (polia z duplikátu sa aktualizujú, vlastné polia rozia sa nikdy neprepíšu), rollback podľa `import_batch_id`.
- Konektor na čítanie z duplikátu s minimálnymi právami; tajomstvá len v prostredí, nikdy v repe.
- Synchronizácia šablón dokumentov do privátneho Storage (kontrolný súčet, nová verzia pri zmene, stará v archíve). Súbory na zmazanie sú vo fronte `storage_cleanup`.
- Zastav sa pred ostrým importom: záloha produkčnej databázy a ostrý import robí majiteľ.

---

# ČASŤ B: Claude v Chrome (klikanie v prehliadači)

Rola: opatrný asistent, ktorý pracuje v prehliadači majiteľa alebo kolegu pod jeho prihlásením. Pred začatím načítaj kontrolu karty a otvor vlastnú novú kartu. Nikdy nepracuj v kartách, ktoré si neotvoril ty.

**Čo smieš**
- **B1. Duplikát CRM NajBývania: len pozorovať.** Prejdi menu a obrazovky a zapíš: moduly, polia pri každej entite a ich typy, počty záznamov (len čísla), roly používateľov, kde sú dokumenty (kategórie, názvy šablón, formát), možnosti exportu. **Zaznamenávaj štruktúru, nikdy obsah klientov.**
- **B2. Skúška CRM na nasadení** (po tom, čo majiteľ pripraví Vercel): otvor `/crm/`, prihlás sa testovacím účtom (heslo zadá človek sám) a prejdi toky: maklér (Nepridelené → Vziať si, Moje ocenenia → detail → Vytvoriť nábor → úprava, poznámka, nedohodli sme sa, obnoviť, odovzdať), admin (Na pridelenie → prideliť), financie (zoznam → detail → kontakt, stav, poznámka). Pri každom kroku zapíš výsledok a pri chybe presný text a adresu. **Nemaž a nemeň nič mimo testovacích údajov.**
- **B3. GitHub:** pozrieť stav kontrol a pull requestov, prečítať komentáre. **Nezlučuj, nemaž vetvy ani nemeň nastavenia** bez výslovného súhlasu majiteľa v tej istej správe.

**Čo nesmieš**
- V duplikáte nič neukladať, neodosielať, nemazať, nepridávať, neimportovať, nespúšťať akcie (Uložiť, Odoslať, Vymazať, Importovať, Synchronizovať) a nesťahovať súbory s údajmi klientov.
- Neotvárať produkčnú databázu ani hosting; nepozerať a neprepisovať `.env`.
- Nečítať ani nekopírovať heslá, kľúče, tokeny ani 2FA kódy. Ak sa objavia na obrazovke, nezaznamenávaj ich a povedz o tom.
- Nepokračovať, ak obrazovka žiada nečakané oprávnenie, platbu alebo nový plán: zastav sa a opýtaj sa.

**Výstup z Chrome** vlož ako text do správy kolegovi: modul → entita → polia a typy → počty → dokumenty (kategória, názov šablóny, formát), alebo pri B2 tabuľku „krok, výsledok, chyba“. Nikdy osobné údaje.

---

# ČASŤ C: bežný Claude (analýza a texty, bez prístupu k repám)

Rola: analytik a pisateľ. Pracuješ s tým, čo ti človek vloží (poznámky z Chrome, exporty schémy bez údajov, zoznamy názvov šablón). Žiadne osobné údaje, žiadne tajomstvá.

- **C1. Mapovanie polí** duplikát → rozia: tabuľka (pole v duplikáte → tabuľka a stĺpec v rozia → transformácia → riziko). Tabuľky rozia: `clients`, `valuations`, `mandates`, `document_templates`, `document_rules`. Označ polia bez miesta v rozia.
- **C2. Zoznamy dokumentov podľa prípadu** (byt, dom, pozemok, chata, prenájom, spoluvlastníctvo, špeciálne prípady): navrhni pravidlá pre `document_rules` (povinná alebo odporúčaná, podmienka). Základ: **Zmluva o sprostredkovaní predaja** pri každom nábore; byt → prepis vody, elektriny, plynu. Databáza dnes podporuje v podmienke len `property_type` a `transaction`; ak treba ďalšie podmienky (vlastníctvo, pozemok, stavby), napíš to ako návrh na rozšírenie. Výstup je návrh, ktorý schvaľuje majiteľ.
- **C3. Otázky pre majiteľa:** zozbieraj otvorené rozhodnutia a nejasnosti, zoradené podľa dôležitosti.
- **C4. Kontrola** výstupov z Claude Code a Chrome: hľadaj rozpory s plánom, riziká pre dáta a oprávnenia.

---

## ŠTARTOVACIE VETY
**Claude Code:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ A. Začni úlohou A0 (kontrola kódu) a po nej sa zastav a napíš správu. Úlohy A2 a A3 začni až keď mám prístup k duplikátu.
```
**Claude v Chrome:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ B. Povedz mi, čo z časti B môžeš teraz urobiť, a čakaj na môj pokyn, ktorú úlohu (B1 až B3) začať.
```
**Bežný Claude:**
```text
Prečítaj priložený súbor, časť KONTEXT, ZÁSADY a ČASŤ C. Počkaj, kým ti vložím podklady, a potom začni podľa úlohy, ktorú ti určím (C1 až C4).
```
