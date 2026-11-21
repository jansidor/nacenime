# Pracovný prompt 1: technicky náročné časti (výkonnejší model)

Pre kolegu s väčším predplatným. Skopíruj všetko pod čiarou do Claude Code, ideálne na najvýkonnejšom dostupnom modeli. Prompt neobsahuje tajomstvá ani mená; prístupy a mená dostane Claude od teba počas práce.

---

## ROLA A CIEĽ
Si senior inžinier. Pracuješ na integrácii troch projektov jednej firmy (NajBývanie Ružomberok, s. r. o.). Tvoja časť je **technicky najnáročnejšia**: audit a napojenie na duplikát CRM NajBývania, import dát, synchronizácia, bezpečnosť, oprávnenia (RLS) a prepojenie nacenime.sk → CRM rozia.sk.

Druhý človek rieši súbežne prompt 2 (Supabase, prostredie, základ aplikácie, obrazovky). **Nepokrývajte tie isté súbory.** Hranice sú v časti „Rozdelenie práce“.

## REPÁ A KONTEXT
- `jansidor/nacenime` (PHP, SQLite, živý web, **verejné repo**) – **tu je hlavný plán**: `docs/PLAN-INTEGRACIE.md`. **Najprv ho celý prečítaj.** Je to záväzný zdroj rozhodnutí (kap. 2a tok, 2b pobočky, kap. 3 bezpečnostné zásady, fázy F0–F11).
- `jansidor/rozia.sk` (Next.js 16, Supabase, zatiaľ len verejný web)
- `jansidor/nacenime-marketing` (prázdne, nerieš)
- Vetva na prácu: `claude/<tvoj-popis>`, nikdy priamo `main`. `main` v nacenime sa automaticky nasadzuje na živý web. Zmeny cez pull requesty, pred zlúčením spusti testy (`README.md` v nacenime).

## ZÁSADY, KTORÉ SA NESMÚ PORUŠIŤ
1. **Originálne CRM firmy sa nedotýkaš.** Pracuješ len s duplikátom a ten najprv len čítaš (DB používateľ s `SELECT` alebo export). Ak by existovala čo i len možnosť zápisu do originálu, zastav sa a opýtaj sa.
2. **Tajomstvá** (heslá, API kľúče, connection stringy, `.env`) nikdy do repa, PR, commitov ani chatu. Patria do `shared/.env` (nacenime), Vercel a Supabase.
3. **Osobné údaje klientov** nikdy do repa ani do logov. Testy len s vymyslenými alebo anonymizovanými dátami.
4. **Len pridávajúce migrácie.** Nič sa nemaže ani nepremenúva, kým nový stav nebeží 2 týždne.
5. **Najprv staging, potom produkcia.** Pred zásahom do produkčných dát záloha s overeným obnovením.
6. **Každý prenos je idempotentný** (`zdroj` + `externe_id`), každý krok má rollback napísaný v PR.
7. **Jednosmerný tok** duplikát → rozia. Žiadny zápis späť.
8. **Repo nacenime je verejné.** Žiadne mená ľudí, telefóny, e-maily, ani reálne ukážky dát v dokumentácii.
9. Dizajn nacenime frontendu sa riadi `design_handoff_nacenime/README.md`; väčšie zmeny vzhľadu najprv navrhni a nechaj schváliť.
10. Ak narazíš na rozpor medzi plánom a realitou kódu, **neprepisuj plán potichu**: napíš rozdiel, navrhni úpravu, nechaj potvrdiť.

## ROZDELENIE PRÁCE
**Ty robíš:** F2 (audit duplikátu), F5 (napojenie na duplikát), F6 (nacenime → rozia), bezpečnosť a RLS testy pre tieto toky, GDPR synchronizácia výmazu, monitoring tokov.
**Druhý človek robí:** Supabase projekty a prostredia, základ aplikácie (prihlásenie, role, obrazovky), CI pre rozia, dátový model tabuliek (migrácie `0002+`). **Dohoda:** názvy tabuliek a stĺpcov, ktoré potrebuješ, sa dohodnú cez `docs/` (zápis v `rozia.sk/docs/ADR-*.md`) pred tým, ako ich kto použije. Migrácie píše jeden z vás, nie obaja: **migrácie pre vstupné tabuľky (`inbound_events`, `import_batches`, `sync_runs`, mapovanie zdroja) píšeš ty**, ostatné druhý človek.

## ÚLOHY (poradie je záväzné)

### A. Duplikát CRM NajBývania (F2) – ihneď po získaní prístupu
Prístup k duplikátu zabezpečuje majiteľ; ty ho získaš ako **prístup len na čítanie**.
1. (I-021, I-022) Over, že duplikát je oddelený od originálu: iná databáza, iné prihlasovacie údaje, žiadna replikácia späť. Výsledok zapíš (bez tajomstiev).
2. (I-023) Zisti technológiu: jazyk, framework, databáza a verzie, hosting, nasadzovanie.
3. (I-024, I-025) Zmapuj schému: tabuľky, vzťahy, počty riadkov, entity (klienti, nehnuteľnosti, obchody, makléri, úlohy, dokumenty, prílohy). Výstup: ER diagram + slovník polí v `docs/duplikat/` (bez osobných údajov).
4. (I-026) Používatelia, heslá, role v duplikáte (hashovanie, 2FA).
5. (I-027) Kde sú súbory (fotky, zmluvy), koľko miesta zaberajú.
6. (I-028) Kvalita dát: duplicity klientov, prázdne polia, formáty telefónov, kódovanie diakritiky.
7. (I-029, I-030) Bezpečnostný audit a licencia/vlastníctvo kódu duplikátu.
8. (I-089) **Dokumenty/šablóny:** kde sú, formát (očakávame prevažne Word/DOCX, niektoré PDF, staršie .doc), kategórie, verzie, či majú vyznačené polia na vyplnenie.
9. (I-031) Správa z auditu s odporúčaním **spôsobu napojenia** (DB len na čítanie / API / export) a frekvencie synchronizácie. **Zastav sa a nechaj majiteľa rozhodnúť** (I-032), kým nepôjdeš do F5.

### B. Prepojenie nacenime.sk → CRM rozia (F6) – dá sa robiť už teraz, bez duplikátu
10. (I-069) Spec udalosti `lead.created` v1 (JSON z `backend/src/Oznamovac.php::data()` + `verzia`, `udalost_id`). Zapíš do `docs/openapi.yaml`.
11. (I-070) nacenime: HMAC-SHA256 podpis existujúceho webhooku (`X-Nacenime-Signature` + časová pečiatka proti replay). Tajomstvo `NACENIME_LEAD_WEBHOOK_SECRET` len v `shared/.env`. **Bez tajomstva sa chová ako doteraz** (spätná kompatibilita). Testy do `backend/tests/oznamenia.php` alebo nový súbor, pridať do CI.
12. (I-071) Stabilné `udalost_id` (`nacenime:kontakt:<id>`).
13. (I-072, I-073) rozia: endpoint príjmu (napr. `POST /api/inbound/nacenime`): overí podpis a vek správy, uloží **surovú udalosť** do `inbound_events`, vráti 2xx; samostatné spracovanie `inbound_events` → `leads`/klienti cez upsert podľa (`zdroj`, `externe_id`); chybné udalosti ostávajú na ručné riešenie.
14. (I-075) Testy: duplicitné doručenie, zlý podpis, starý timestamp, neplatné JSON, výpadok DB, veľké telo.
15. (I-076) Celý tok na **stagingu**: kalkulačka → webhook → rozia staging.
16. (I-081) Synchronizácia GDPR výmazu: `bin/gdpr.php zmaz` pošle udalosť `lead.deleted`, CRM údaje anonymizuje.
17. (I-080, I-099) Skript na porovnanie počtu leadov nacenime vs. CRM (bez osobných údajov vo výstupe) a monitoring (chybné `inbound_events`).
**Ostré zapnutie (I-078) robí majiteľ**: až po zverejnení upravených zásad ochrany OÚ (I-077). Ty pripravíš návod a kontrolný zoznam, neaktivuješ to.

### C. Napojenie na duplikát (F5) – po rozhodnutí majiteľa
18. (I-056b) Konektor len na čítanie, minimálne práva, tajomstvá mimo repa.
19. (I-056a) Pravidlo konfliktov: polia z duplikátu sa aktualizujú, vlastné polia rozia (poznámky, Postup, dokumenty) sa nikdy neprepíšu.
20. (I-057, I-058) Mapovanie polí duplikát → nový model; importný skript s **dry-run** (nič nezapíše, vypíše štatistiky).
21. (I-059, I-060) Anonymizovaný výrez a import na staging + porovnávacie kontroly (počty, súčty, náhodné vzorky 1:1).
22. (I-061, I-062, I-063) Súbory do privátneho Storage s kontrolnými súčtami; párovanie maklérov; deduplikácia klientov (sporné prípady na ručné rozhodnutie, nie automaticky).
23. (I-065) **Zastav sa:** záloha produkčnej DB rozia robí majiteľ. Potom (I-066) prvý ostrý import ⚠️ a (I-066a) priebežná synchronizácia (len zmeny, záznam každého behu, upozornenie pri chybe).
24. (I-067, I-068) Kontrolná správa po importe a rollback podľa `import_batch_id`.
25. (I-089a) Synchronizácia šablón dokumentov duplikát → privátny Storage rozia (len čítanie, kontrolný súčet, zmena = nová verzia, stará v archíve).

## STIL PRÁCE
- Malé PR, každé s popisom: čo, prečo, ako otestovať, **ako sa vrátiť späť**.
- Pred zlúčením: všetky testy zelené (CI v nacenime, `npm run typecheck && npm test` v rozia).
- Po každom bloku A, B, C krátka správa majiteľovi: čo je hotové, čo overené, čo **nebolo** overené, čo čaká na jeho rozhodnutie.
- Neoverené veci pomenuj ako neoverené. Nehlás hotové to, čo si nespustil.
- Keď niečo vyžaduje ľudské rozhodnutie alebo prístup, zastav sa a presne povedz čo a koho.
