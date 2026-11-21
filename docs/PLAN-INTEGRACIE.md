# Plán integrácie: nacenime.sk ↔ rozia.sk ↔ CRM (duplikát NajBývanie)

Stav k 21. 11. 2026 (sobota). Dokument je **plán, nie implementácia**. Každý krok sa robí samostatne cez vetvu a pull request.

Značky: **[C]** urobí Claude · **[T]** urobíš ty (majiteľ, prístupy, rozhodnutia) · **[S]** spolu · 🔒 čaká na duplikát CRM (od pondelka 23. 11.) · ⚠️ dotýka sa produkčných dát

---

## 1. Čo som v repách reálne našiel

| Repo | Technológia | Stav | Dôležité pre integráciu |
|---|---|---|---|
| `jansidor/nacenime` | PHP 8.1, SQLite, Hostcreators (Git → vetva `deploy`) | **živé**, leady prichádzajú | Tabuľky `odhady`, `kontakty`; vlastné CRM fáza 1 (`crm_*`, role Správca/Maklér, `crm.nacenime.sk`). **Webhook pre nové leady už existuje** (`NACENIME_LEAD_WEBHOOK`, `Oznamovac::data()`), s opakovaním (max 5 pokusov), ale **bez podpisu**. GDPR retencia 24 mesiacov. |
| `jansidor/rozia.sk` | Next.js 16, Supabase (Postgres, Frankfurt), Vercel | 1 commit, **len verejný web** | Tabuľky `agents`, `listings`, `sales`, `leads`… s RLS a pripraveným `org_id`. **Moduly CRM, Dokumenty a Postup zatiaľ neexistujú.** Vlastný formulár ocenenia (iný výpočet než nacenime). |
| `jansidor/nacenime-marketing` | – | prázdne (len README) | Zatiaľ nič na integráciu. |
| Duplikát CRM NajBývanie | **neznáma** | k dispozícii od pondelka 🔒 | **Zdroj dát** pre CRM rozia (nie samotné CRM rozia). Technológia a databáza rozhodnú, ako sa naň napojíme. |

Dôsledok: schéma z obrázka je **cieľový stav**. Dnes existuje nacenime (živé) a verejný web rozia.

**Upresnenie (21. 11.):** rozia.sk **má vlastné CRM** (stavia sa v rozia.sk, Supabase). Duplikát CRM NajBývania **nie je** CRM rozia, je to **zdroj dát**, na ktorý je CRM rozia napojené. Prerušovaná čiara na obrázku = originál NajBývania → duplikát (tam sa nič nemení); plná väzba = dáta z duplikátu → CRM rozia. Dokumenty a Postup sú moduly CRM rozia.

## 2. Rozhodnutia, ktoré treba urobiť (nie technické, ale blokujú ďalšie kroky)

| # | Otázka | Môj návrh | Kedy |
|---|---|---|---|
| R1 | ~~Kde bude žiť CRM jadro?~~ **Rozhodnuté:** vlastné CRM rozia (Supabase). Duplikát NajBývania je zdroj dát. | Ostáva rozhodnúť **spôsob napojenia** (priamy prístup do DB duplikátu len na čítanie / API / pravidelný export) – po audite F2. | po F2 🔒 |
| R2 | ~~Čo s CRM na `crm.nacenime.sk`?~~ **Rozhodnuté 21. 11.:** ocenenia sa musia **automaticky rozdeliť maklérom podľa lokality**; maklér ich vidí **len v CRM rozia**, nie v nacenime. | Pridelenie podľa územia makléra (kap. 2b). Pravidlo beží **pri príjme v rozia** (tam sú účty a územia maklérov, jedno miesto na správu); nacenime len odošle ocenenie. `crm.nacenime.sk` beží paralelne do overenia, potom vypnúť. | **potvrdené** |
| R3 | ~~Kto je prevádzkovateľ osobných údajov?~~ **Rozhodnuté 21. 11.:** nacenime.sk, rozia.sk a NajBývanie sú **jedna firma** (NajBývanie Ružomberok, s. r. o.), rozia je rozšírenie možností. | Prenos leadov medzi systémami je **interný** (nie odovzdanie inej firme). Treba ešte: zásady ochrany OÚ na nacenime aj rozia musia uvádzať rovnakého prevádzkovateľa a účel „spracovanie dopytu v našom CRM“ (I-018, I-077). | **potvrdené** |
| R4 | ~~Čo je „Databáza ponúk“?~~ **Rozhodnuté 21. 11.:** sú to **dopyty z kalkulačky** (ocenenia domov, bytov, pozemkov), ktoré klient odoslal. | Tok je popísaný v kapitole 2a. | **potvrdené** |
| R5 | Napojenie CRM rozia na duplikát: **jednorazový import**, alebo **priebežné** (dáta pribúdajú v duplikáte a majú sa objavovať v CRM rozia)? A má CRM rozia niekedy **zapisovať späť** do duplikátu? | Priebežné, **jednosmerné, len čítanie** (duplikát → rozia). Zápis späť nie. Originál firmy sa nikdy nečíta ani nezapisuje automaticky. | **potvrdené 21. 11.** |
| R6 | ~~Pobočky?~~ **Rozhodnuté 21. 11.:** jedna firma, **4 pobočky**: Liptovský Mikuláš, Ružomberok, Dolný Kubín (celá Orava), Martin/Vrútky (celý Turiec). | Pobočka = `branches` v rámci jednej organizácie, makléri patria pobočke. Role: maklér (svoje) / admin (všetko). | **potvrdené** vrátane okresov |
| R7 | Rozpočet a hosting: Supabase plán (zálohy, PITR), Vercel plán. | Supabase Pro kvôli denným zálohám a PITR pred ostrými dátami CRM. | F4 [T] |

## 2a. Hlavný obchodný tok (potvrdený majiteľom 21. 11.)

```
Klient na nacenime.sk          CRM rozia (priestor makléra)
─────────────────────          ─────────────────────────────────────────────
1. ocení dom/byt/pozemok
2. zadá e-mail → príde mu
   hrubé ocenenie (PDF)
3. ocenenie so súhlasom   ──►  4. automatické pridelenie maklérovi podľa lokality
   s kontaktovaním                (obec → okres → pobočka → maklér; napr. Vrútky → pobočka Martin/Vrútky)
                               5. maklér vidí ocenenie len vo svojom CRM, kontaktuje klienta
                               6. vytvorí NÁBOR – predvyplnené: meno, kontakt,
                                  popis nehnuteľnosti od klienta, odhad
                               7. systém NAVRHNE DOKUMENTY:
                                  • vždy: Zmluva o sprostredkovaní predaja (prioritná)
                                  • podľa typu/špecifík: napr. byt → prepis vody,
                                    elektriny, plynu; ďalšie podľa nehnuteľnosti
                               8a. dohodnú sa → nábor uložený v databáze makléra
                               8b. nedohodnú sa → nábor vymazaný
```

Pravidlá toku (potvrdené majiteľom 21. 11.):
- **Kontaktovanie:** klient, ktorý zadá **telefónne číslo** („Zavolajte mi“), tým žiada o kontakt – ocenenie ide maklérovi. Kto nechce byť volaný, číslo nezadá. Ocenenie len s e-mailom ide maklérovi iba vtedy, keď klient zaškrtol súhlas s kontaktovaním (tak to funguje dnes). Ocenenie bez telefónu aj bez súhlasu ostáva len v štatistike nacenime.
- **Nedohodnutý nábor sa nevymazáva úplne – ide do archívu.** Vymažú sa rozpracované dokumenty náboru, ale v CRM ostane **meno a priezvisko, telefón, e-mail a stručný popis nehnuteľnosti** (typ, lokalita, výmera, odhad) + dôvod a dátum. Klient je tak na dosah, keby sa vrátil; nábor sa dá z archívu **obnoviť**. Ocenenie dostane stav **Stratené + dôvod**.
  - GDPR: uchovanie kontaktu po nedohode = oprávnený záujem s obmedzenou dobou (návrh 24 mesiacov ako v nacenime, potom automatická anonymizácia). Musí to byť uvedené v zásadách ochrany OÚ a klient môže kedykoľvek požiadať o výmaz (I-018, I-043).
- **Ocenenie bez makléra** (lokalita bez určeného makléra/pobočky) sa nikdy nestratí – ide do kategórie **„Nepridelené“**:
  - admin ho môže prideliť ručne, **alebo**
  - **ktorýkoľvek maklér si ho môže zobrať** (po dohode s ostatnými). Kto si ho vezme prvý, toho je – ostatným zmizne zo zoznamu (ochrana proti tomu, aby si ho vzali dvaja naraz). Zapíše sa do histórie, kto a kedy si ho vzal.
  - Ak si ho nikto nevezme do 24 h, admin dostane pripomienku.

## 2b. Pobočky a územia (potvrdené 21. 11.)

Kalkulačka nacenime pokrýva 7 okresov. Návrh, ktorá pobočka dostáva ocenenia odkiaľ:

| Pobočka | Okresy (ocenenia z týchto obcí) |
|---|---|
| **Liptovský Mikuláš** | Liptovský Mikuláš |
| **Ružomberok** | Ružomberok |
| **Dolný Kubín** | Dolný Kubín, Tvrdošín, Námestovo (celá Orava) |
| **Martin / Vrútky** | Martin, Turčianske Teplice (celý Turiec) |

Pravidlo pridelenia (potvrdené 21. 11.):
- **Fáza 1 – štart:** všetky ocenenia (pridelené podľa pobočky aj nepridelené) **rozdeľujú ručne dvaja admini** (mená sú v nastaveniach CRM, nie vo verejnom repe). Systém im ukáže navrhnutú pobočku podľa okresu.
- **Fáza 2 – po zabehnutí:** automaticky **obec → okres → pobočka → striedavo medzi maklérmi pobočky** (rovnomerne, aby dostal každý). Admini môžu pridelenie kedykoľvek zmeniť. Prepnutie fázy 1 → 2 je nastavenie, nie nové nasadenie.
- **Odovzdanie kolegovi:** maklér, ktorému bolo ocenenie/nábor pridelené, ho môže **sám odovzdať inému maklérovi tej istej pobočky** (dovolenka, PN, nevie to vyriešiť) a sám si vyberie komu. Kolega dostane oznámenie, zapíše sa do histórie kto, komu, kedy a prečo. Spolu s náborom prechádzajú poznámky a dokumenty.
- **Obsadenie pobočiek (21. 11., mená sú len v CRM, nie v repe):** Ružomberok 7 (vrátane oboch adminov), Liptovský Mikuláš 2, Dolný Kubín / Orava 1, Martin / Vrútky 1 = **11 aktívnych**. Ďalej 1 účet **neaktívny** (dočasne mimo SK – nedostáva ocenenia, dá sa znova aktivovať) a 1 osoba z **finančného oddelenia** (rola Financie: nedostáva ocenenia, vidí všetky začaté nábory a vedie stav financovania – I-088a). Duplicitný účet jednej osoby sa nezakladá.
- **Pobočky s jedným maklérom (Dolný Kubín, Martin/Vrútky) – potvrdené 21. 11.:** keď je maklér neprítomný (PN, dovolenka, neaktívny), nové ocenenia z jeho pobočky idú **automaticky do Ružomberka** (záložná pobočka, tam sa striedajú); admin ich môže presunúť aj inam. Maklér môže pred neprítomnosťou svoje rozpracované nábory odovzdať kolegovi v Ružomberku alebo inej pobočke. Záložná pobočka je nastavenie (pri každej pobočke), nie pevne v kóde.
- Výnimky po obciach (obec na hranici okresov obsluhuje iná pobočka) sa dajú nastaviť bez programovania.

## 3. Bezpečnostné zásady (platia pre každý krok)

1. **Originálne CRM firmy sa nikdy nedotýkame.** Pracujeme len s duplikátom a aj ten najprv len čítame.
2. **Najprv staging, potom produkcia.** Samostatný Supabase projekt a samostatná databáza pre testy; produkčné dáta sa do stagingu kopírujú len anonymizované.
3. **Záloha pred každým zásahom** do produkčných dát, s overeným obnovením (záloha, ktorú sme neskúsili obnoviť, sa nepočíta).
4. **Len pridávajúce migrácie** (expand → presun → contract). Nič sa nemaže ani nepremenúva, kým nový stav nebeží aspoň 2 týždne.
5. **Jednosmerný tok, idempotentne.** Každý záznam má `zdroj` + `externe_id`; opakované odoslanie nevytvorí duplikát.
6. **Prepínače (feature flags)** pre každú novú cestu dát; vypnutie = návrat do pôvodného stavu bez nasadenia.
7. **Paralelný beh:** nová cesta beží popri starej (e-mail o leade + webhook), kým sa čísla nezhodujú.
8. **Tajomstvá** len v `shared/.env` (nacenime), vo Verceli a v Supabase. Nikdy v repe, PR ani chate. Repo nacenime je verejné.
9. **Každý krok má rollback** zapísaný v PR (ako sa vrátiť späť).
10. **Žiadny „veľký tresk“.** Malé PR, testy v CI, zlúčenie až po kontrole.

---

## 4. Plán po fázach

### F0 · Poistky a príprava (víkend, dá sa hneď)
- [x] **I-001** [T] Potvrdiť R3 (jedna firma) a R5 (priebežne, jednosmerne, len čítanie) – potvrdené 21. 11.
- [ ] **I-002** [C] Zapnúť ochranu vetvy `main` v `nacenime` (PR povinný, CI musí prejsť) – návod pre teba, nastavuješ ty v GitHube. [T]
- [ ] **I-003** [C] Pridať CI do `rozia.sk` (`typecheck`, `test`, `build`, `check:contrast`) – zatiaľ tam CI chýba.
- [ ] **I-004** [T] Overiť, že beží denná záloha nacenime (`zaloha-db.php` v crone) a stiahnuť jednu zálohu mimo hosting.
- [ ] **I-005** [S] Skúšobne obnoviť zálohu nacenime lokálne a spustiť nad ňou `php backend/tests/*.php` (overenie, že záloha je použiteľná). ⚠️ len čítanie
- [ ] **I-006** [T] Zistiť stav Supabase projektu rozia: existuje? je v ňom niečo? aký plán? zapnuté zálohy/PITR?
- [ ] **I-007** [T] Založiť **staging** Supabase projekt (Frankfurt) a staging prostredie vo Verceli (preview).
- [ ] **I-008** [C] Pridať do `rozia.sk` priečinok `docs/` s odkazom na tento plán a ADR šablónou (záznamy rozhodnutí).
- [ ] **I-009** [S] Dohodnúť, kde sa sledujú úlohy (GitHub Issues v `rozia.sk` s popiskami `faza-X`, alebo tento súbor).
- [ ] **I-010** [T] Pripraviť zoznam ľudí a rolí (kto je admin, ktorí makléri) – bez hesiel, len mená a role.

### F1 · Inventár existujúcich systémov (víkend, bez duplikátu)
- [ ] **I-011** [C] Zdokumentovať dátový model nacenime (`odhady`, `kontakty`, `crm_*`) ako ER diagram + slovník polí.
- [ ] **I-012** [C] Zdokumentovať dátový model rozia (`0001_public_web.sql`) ako ER diagram.
- [ ] **I-013** [C] Porovnať lead nacenime ↔ `leads` rozia: mapovanie polí, čo chýba (napr. `stav`, `pridelene`, odhad od–do, adresa, GPS).
- [ ] **I-014** [C] Porovnať typy nehnuteľností a stavy: nacenime (`Byt`, `Dom`, `Kompletná rekonštrukcia`…) vs. rozia (`byty`, `domy`, `rekonstrukcia`…) → prevodná tabuľka.
- [ ] **I-015** [C] Porovnať lokality: nacenime (obce 7 okresov z CSV) vs. rozia (`localities.ts`, slugy) → jednotný číselník obcí.
- [ ] **I-016** [C] Popísať stavy leadu CRM nacenime (Nový → … → Predané/Stratené) ako kandidáta na spoločný štandard.
- [ ] **I-017** [C] Zmapovať všetky miesta, kde vznikajú osobné údaje (formuláre nacenime, formuláre rozia, CRM) a ich retenciu.
- [ ] **I-018** [C] Zmapovať texty súhlasov a zásady ochrany OÚ (nacenime aj rozia): rovnaký prevádzkovateľ (NajBývanie Ružomberok, s. r. o.), účely „spracovanie v CRM“, „archív nedohodnutých náborov (24 mes.)“ a „sprostredkovanie financovania“, rovnaká doba uchovávania. Doplniť zákonnú identifikáciu do `rozia.sk/lib/site.ts` (dnes `null`).
- [ ] **I-019** [C] Analýza rizík: čo sa pokazí, ak webhook zlyhá / príde dvakrát / príde podvrhnutý.
- [ ] **I-020** [S] Prejsť výstupy F1 spolu (30 min), opraviť nepresnosti.

### F2 · Audit duplikátu CRM 🔒 (od pondelka)
- [ ] **I-021** 🔒 [T] Získať prístup k duplikátu **len na čítanie** (DB používateľ s `SELECT`, alebo export/dump). Nie prístup do originálu.
- [ ] **I-022** 🔒 [T] Potvrdiť, že duplikát je naozaj oddelený od originálu (iná DB, iné prihlasovacie údaje, žiadna replikácia späť).
- [ ] **I-023** 🔒 [C] Zistiť technológiu: jazyk, framework, databáza, verzie, hosting, ako sa nasadzuje.
- [ ] **I-024** 🔒 [C] Zmapovať schému databázy (tabuľky, vzťahy, počty riadkov) – bez kopírovania osobných údajov do repa.
- [ ] **I-025** 🔒 [C] Identifikovať entity: klienti, nehnuteľnosti, obchody, makléri, úlohy, dokumenty, súbory/prílohy.
- [ ] **I-026** 🔒 [C] Zistiť, ako duplikát rieši používateľov, heslá a role (hashovanie, 2FA).
- [ ] **I-027** 🔒 [C] Zistiť, kde sú súbory (fotky, zmluvy) a koľko miesta zaberajú.
- [ ] **I-028** 🔒 [C] Kvalita dát: duplicity klientov, prázdne polia, formáty telefónov, kódovanie diakritiky.
- [ ] **I-029** 🔒 [C] Bezpečnostný audit duplikátu (zjavné diery, závislosti so známymi zraniteľnosťami).
- [ ] **I-030** 🔒 [C] Licencia a vlastníctvo kódu duplikátu – môžeme ho upravovať? [T] potvrdiť.
- [ ] **I-031** 🔒 [C] Správa z auditu + odporúčanie **spôsobu napojenia** (DB len na čítanie / API / export) a frekvencie synchronizácie.
- [ ] **I-032** 🔒 [T] Rozhodnúť spôsob napojenia, R5 a R2.

### F3 · Cieľová architektúra a dátový model
- [ ] **I-033** [C] ADR-001: kde je „zdroj pravdy“ pre každú entitu (lead, klient, nehnuteľnosť, maklér, dokument, záznam Postupu).
- [ ] **I-034** [C] ADR-002: spôsob prepojenia nacenime → CRM. Odporúčanie: **podpísaný webhook (HMAC) + outbox v nacenime + idempotentný príjem v rozia**; nie zdieľaná databáza (SQLite na zdieľanom hostingu sa nedá bezpečne zdieľať).
- [ ] **I-035** [C] ADR-003: autentifikácia – Supabase Auth (e-mail + magic link, neskôr 2FA pre adminov).
- [ ] **I-036** [C] ADR-004: role a oprávnenia – `makler` (vidí len svoje), `admin` (vidí všetko v organizácii), `superadmin` (technická správa). Vynucuje **RLS v databáze**, nie len UI.
- [ ] **I-037** [C] Dátový model CRM jadra: `organizations`, `profiles` (maklér ↔ auth user), `clients`, `properties`, `deals`, `activities`, `tasks`.
- [ ] **I-038** [C] Dátový model **Postup** = databáza náborov makléra (`nabory`, `owner_id`), nehnuteľnosť, kroky/fázy, stav, termíny, poznámky; RLS „len vlastník + admin“.
- [ ] **I-039** [C] Dátový model **Dokumenty**: `document_templates` (zdroj = duplikát, externe_id, verzia, kontrolný súčet, súbor len na čítanie), `template_fields` (mapovanie polí na CRM), `client_documents` (vyplnená kópia: klient, nehnuteľnosť, maklér, verzia šablóny, stav Návrh/Skontrolované, súbory). Pozri F8.
- [ ] **I-040** [C] Dátový model toku (kap. 2a): `leads` (ocenenie z nacenime), `branches` (4 pobočky), `branch_districts` (pobočka ↔ okresy, výnimky po obciach), `agent_territories` (maklér ↔ obce v rámci pobočky), `nabory` (maklér, klient, nehnuteľnosť, väzba na lead, stav Rozpracovaný/Uložený), `document_rules` (podmienka → šablóna, povinná/odporúčaná), `nabor_documents` (navrhnuté/vybrané/vyplnené dokumenty náboru).
- [ ] **I-041** [C] Spoločné číselníky: obce/lokality, typy, stavy nehnuteľností, stavy leadu (z I-014–I-016).
- [ ] **I-042** [C] Audit log: kto, kedy, čo zmenil (najmä pri osobných údajoch a dokumentoch).
- [ ] **I-043** [C] GDPR návrh: retencia, export a výmaz naprieč systémami (výmaz v nacenime musí dobehnúť aj do CRM).
- [ ] **I-044** [C] Diagram tokov dát (aktualizovaná verzia obrázka, so smermi a formátmi).
- [ ] **I-045** [S] Revízia architektúry, schválenie ADR-001 až 004.
- [ ] **I-046** [T] Rozhodnúť R4, R6, R7.

### F4 · Základ platformy rozia.sk (prihlásenie, role, staging)
- [ ] **I-047** [C] Rozhodnúť štruktúru: interná aplikácia ako `app.rozia.sk` (samostatná časť v tom istom repe, `app/(app)/…`) oddelená od verejného webu.
- [ ] **I-048** [C] Migrácia `0002_core.sql`: `organizations`, `profiles`, role – **len pridávajúca**, existujúce tabuľky bez zmeny.
- [ ] **I-049** [C] RLS politiky pre nové tabuľky + automatické testy RLS (maklér A nevidí dáta makléra B, admin vidí všetko).
- [ ] **I-050** [C] Prihlásenie (Supabase Auth), odhlásenie, obnova hesla, pozvánka makléra adminom.
- [ ] **I-051** [C] Ochrana trás (middleware), session len cez httpOnly cookies.
- [ ] **I-052** [C] Základné rozhranie: navigácia CRM / Dokumenty / Postup, prázdne obrazovky.
- [ ] **I-053** [C] Seed dát pre staging (vymyslené, žiadne reálne osobné údaje).
- [ ] **I-054** [C] CI: migrácie sa spúšťajú na čistej DB v CI, RLS testy povinné.
- [ ] **I-055** [T] Nasadiť na staging, vyskúšať ako maklér a ako admin.
- [ ] **I-056** [C] Monitoring chýb (napr. Sentry alebo Vercel logy) bez osobných údajov v logoch.

### F5 · Napojenie CRM rozia na dáta z duplikátu NajBývania 🔒
*CRM rozia je vlastné; duplikát je zdroj. Tok je jednosmerný (duplikát → rozia), duplikát len čítame. Každý prenesený záznam nesie `zdroj = 'najbyvanie'` + `externe_id`, aby opakovaná synchronizácia neprepisovala ani nezdvojovala dáta a aby bolo jasné, čo prišlo z duplikátu a čo vzniklo v rozia.*
- [ ] **I-056a** 🔒 [C] Pravidlo konfliktov: ak maklér upraví v CRM rozia záznam, ktorý prišiel z duplikátu, čo vyhrá pri ďalšej synchronizácii? Návrh: polia z duplikátu sa aktualizujú, vlastné polia rozia (poznámky, Postup, dokumenty) sa nikdy neprepisujú.
- [ ] **I-056b** 🔒 [C] Konektor len na čítanie (samostatný DB používateľ / API kľúč s minimom práv), tajomstvá vo Verceli/Supabase, nie v repe.
- [ ] **I-057** 🔒 [C] Mapovanie polí duplikát → nový model (tabuľka, stĺpec po stĺpci, transformácie).
- [ ] **I-058** 🔒 [C] Importný skript s **dry-run** režimom (nič nezapíše, len vypíše, čo by spravil, a štatistiky).
- [ ] **I-059** 🔒 [C] Anonymizovaný výrez duplikátu na testovanie importu.
- [ ] **I-060** 🔒 [C] Import na staging + porovnávacie kontroly (počty, súčty, náhodné vzorky 1:1).
- [ ] **I-061** 🔒 [C] Prenos súborov (prílohy, fotky) do privátneho Storage, kontrola kontrolných súčtov.
- [ ] **I-062** 🔒 [C] Párovanie maklérov z duplikátu na nové účty.
- [ ] **I-063** 🔒 [C] Deduplikácia klientov (pravidlá: telefón, e-mail; sporné prípady na ručné rozhodnutie, nie automaticky).
- [ ] **I-064** 🔒 [S] Kontrola na stagingu maklérmi (1–2 ľudia, reálny pocit, či dáta sedia).
- [ ] **I-065** 🔒 [T] Záloha produkčnej DB rozia pred ostrým importom ⚠️
- [ ] **I-066** 🔒 [C] Ostrý prvý import (idempotentný, dá sa spustiť znova bez duplikátov) ⚠️
- [ ] **I-066a** 🔒 [C] Priebežná synchronizácia (ak R5 = priebežne): naplánovaná úloha, len zmeny od poslednej synchronizácie, záznam o každom behu, upozornenie pri chybe.
- [ ] **I-067** 🔒 [C] Kontrolná správa po importe (rovnaké porovnania ako I-060).
- [ ] **I-068** 🔒 [C] Rollback plán: ako import úplne odstrániť podľa `import_batch_id`.

### F6 · Prepojenie nacenime.sk → CRM (plná čiara na obrázku)
*Najmenší zásah do živého nacenime: webhook už existuje, doplní sa podpis a príjemca.*
- [ ] **I-069** [C] Spec udalosti `lead.created` v1 (JSON z `Oznamovac::data()` + `verzia`, `udalost_id`), zapísať do `docs/openapi.yaml`.
- [ ] **I-070** [C] nacenime: HMAC-SHA256 podpis webhooku (`X-Nacenime-Signature`, časová pečiatka proti replay), tajomstvo v `shared/.env` (`NACENIME_LEAD_WEBHOOK_SECRET`). Bez tajomstva sa správa ako doteraz. + testy.
- [ ] **I-071** [C] nacenime: stabilné `udalost_id` (`nacenime:kontakt:<id>`) pre idempotenciu na strane príjemcu.
- [ ] **I-072** [C] rozia: endpoint `POST /api/inbound/nacenime` – overí podpis, vek správy, uloží **surovú udalosť** do `inbound_events` a vráti 2xx.
- [ ] **I-073** [C] rozia: spracovanie `inbound_events` → `leads`/`clients` cez `upsert` podľa (`zdroj`, `externe_id`); chybné udalosti zostanú v tabuľke na ručné riešenie.
- [x] **I-074** [T] Priradenie okresov k pobočkám potvrdené; pravidlo: štart ručne admini, potom striedanie v pobočke. Zoznam maklérov po pobočkách dodaný a doplnený 21. 11. (mimo repa).
- [ ] **I-074e** [C] Fáza 1: fronta „Na pridelenie“ pre adminov (všetky nové ocenenia, s navrhnutou pobočkou), pridelenie jedným klikom. Prepínač fáza 1 / fáza 2 v nastaveniach.
- [ ] **I-074f** [C] Fáza 2: striedavé pridelenie v pobočke (round-robin, preskočí neprítomného/neaktívneho makléra); keď v pobočke nie je nikto dostupný → **záložná pobočka** (DK, MT → RK). Stav makléra: aktívny / neprítomný od–do / neaktívny.
- [ ] **I-074h** [C] Rola **Financie** (potvrdené 21. 11., 1 osoba z finančného oddelenia): vidí **všetky začaté nábory zo všetkých pobočiek** (len čítanie údajov náboru), nedostáva ocenenia, nemôže meniť nábor, prideľovať ani mazať. Môže len: (a) nastaviť **stav financovania** náboru, (b) písať **poznámky k financovaniu**. Podrobnosti v I-088a.
- [ ] **I-074g** [C] **Odovzdať kolegovi**: maklér vyberie kolegu z tej istej pobočky + dôvod; prejde ocenenie aj nábor s poznámkami a dokumentmi; oznámenie kolegovi; záznam v histórii. Odovzdať možno aj do inej pobočky (potvrdené pre malé pobočky); zapíše sa do histórie.
- [ ] **I-074a** [C] Pridelenie podľa lokality: obec z ocenenia → okres → pobočka → maklér; bez makléra → kategória **Nepridelené**. Admin môže pridelenie kedykoľvek zmeniť (zapíše sa do histórie).
- [ ] **I-074d** [C] Kategória **Nepridelené**: vidia ju všetci makléri a admini; tlačidlo **„Vziať si“** (atomicky – prvý vyhráva, druhý dostane hlásenie „už si ho vzal X“); admin môže prideliť ručne; pripomienka adminom po 24 h bez prevzatia. **Limit: maklér si môže vziať najviac 1 nepridelené ocenenie za 24 h** (hodnota nastaviteľná, napr. 48 h), aby si jeden nezobral všetky; limit sa kontroluje na serveri, admina sa netýka. Tlačidlo ukáže, kedy si maklér môže vziať ďalšie.
- [ ] **I-074b** [C] Oznámenie maklérovi o novom ocenení (e-mail s odkazom do CRM rozia, bez osobných údajov v predmete).
- [ ] **I-074c** [C] Testy: každá obec 7 okresov má makléra alebo ide adminovi; maklér nevidí ocenenia iného územia (RLS).
- [ ] **I-075** [C] Testy: duplicitné doručenie, zlý podpis, starý timestamp, neplatné JSON, výpadok DB.
- [ ] **I-076** [C] Nastaviť na **staging**: nacenime (lokálne/staging) → rozia staging. Celý tok od kalkulačky po kartu v CRM.
- [ ] **I-077** [T] Upravené zásady ochrany OÚ (I-018) sú zverejnené na nacenime aj rozia, ideálne po pohľade advokáta. Až potom ostrý prenos leadov.
- [ ] **I-078** [T] Do produkčného `shared/.env` nacenime nastaviť `NACENIME_LEAD_WEBHOOK` a tajomstvo. E-mailové oznámenia **ostávajú zapnuté** (paralelný beh). ⚠️
- [ ] **I-079** [C] Overenie: `/api/health/fronta` v poriadku, prvé leady prišli do CRM.
- [ ] **I-080** [C] Týždenné porovnanie: počet leadov v nacenime vs. v CRM (skript bez osobných údajov vo výstupe), 2 týždne.
- [ ] **I-081** [C] Synchronizácia výmazu (GDPR): `gdpr.php zmaz` v nacenime pošle udalosť `lead.deleted`, CRM údaje anonymizuje.
- [ ] **I-082** [S] Rozhodnúť o CRM na `crm.nacenime.sk` (R2): režim len na čítanie → vypnutie. Dáta z neho (stav, poznámky, pridelenie) preniesť jednorazovým importom, ak sa používali.

### F7 · Modul Postup (nábory makléra)
- [ ] **I-083** [C] Migrácia tabuliek `nabory` + RLS (vlastník + admin) + testy RLS.
- [ ] **I-084** [C] Detail ocenenia v CRM → tlačidlo **„Vytvoriť nábor“**: predvyplní meno, kontakt, adresu, typ, výmeru, stav, popis domu (podlažia, pozemok, stavby) a odhad od–do z nacenime. Maklér doplní zvyšok.
- [ ] **I-084a** [C] Nábor má stav **Rozpracovaný** (rokuje s klientom) → **Uložený** (dohodli sa) alebo **Nedohodnutý** (pýta potvrdenie a dôvod; ocenenie prejde na Stratené).
- [ ] **I-084c** [C] **Archív nedohodnutých**: ostáva meno a priezvisko, telefón, e-mail, stručný popis nehnuteľnosti, dôvod a dátum; rozpracované dokumenty sa zmažú. Vyhľadávanie v archíve a tlačidlo **„Obnoviť nábor“** (klient sa vrátil). Po uplynutí retencie automatická anonymizácia.
- [ ] **I-084d** [C] **Vrátený klient:** pri novom ocenení systém hľadá v archíve aj medzi aktívnymi zhodu podľa telefónu/e-mailu (a mena). Ak ju nájde, **porovná nehnuteľnosť**: lokalita (obec, adresa), typ, výmera, stav, cena/odhad, podrobnosti domu (podlažia, pozemok, stavby).
  - **Zhoda ≥ 80 %** (rovnaká alebo podobná nehnuteľnosť): obnoví pôvodný nábor s tou istou nehnuteľnosťou a **doplní/aktualizuje len zmenené časti** z nového ocenenia; zmeny ukáže maklérovi (pôvodne → teraz) na potvrdenie.
  - **Zhoda < 80 %** (iná nehnuteľnosť): klienta predvyplní z existujúceho záznamu a vytvorí **nový nábor** pre novú nehnuteľnosť.
  - Maklér vidí „klient sa vrátil, predtým nábor z dd.mm.rrrr“ a výsledok môže ručne zmeniť (spojiť / oddeliť). Nič sa neprepíše bez záznamu v histórii.
- [ ] **I-084e** [C] Testy porovnania: rovnaká adresa a typ s inou cenou → obnova + aktualizácia ceny; iná obec alebo typ → nový nábor; váhy polí a hranica 80 % nastaviteľné.
- [ ] **I-084b** [C] Obrazovky: zoznam náborov makléra, detail, kroky, termíny, poznámky.
- [ ] **I-085** [C] Admin prehľad naprieč maklérmi (len čítanie, filtre po maklérovi, konverzia ocenenie → nábor).
- [ ] **I-086** [C] Väzba nábor ↔ klient/nehnuteľnosť v CRM (aj dáta z duplikátu NajBývania), nie duplicitné kópie.
- [ ] **I-087** [C] Import existujúcich náborov, ak ich makléri vedú dnes v Exceli (šablóna + dry-run).
- [ ] **I-088a** [C] **Financovanie v nábore** (pre rolu Financie):
  - Stav financovania: **Nové** (nikto neriešil) → **Príprava** (ide sa riešiť) → **Rieši sa** → **Vyriešené**; bokom **Nerieši sa** (klient nechce / financie cez nás zrušené, s dôvodom).
  - Zoznam pre Financie: všetky začaté nábory s filtrom podľa stavu financovania, pobočky a makléra; nové nábory zvýraznené.
  - Poznámky k financovaniu sú oddelené od poznámok makléra; maklér ich vidí na svojom nábore spolu so stavom financovania.
  - Každá zmena stavu ide do histórie (kto, kedy, z čoho na čo).
  - **Financie vidí meno, telefón a e-mail klienta** (potvrdené 21. 11.) a môže ho kontaktovať kvôli financovaniu. GDPR: účel „sprostredkovanie financovania“ doplniť do zásad ochrany OÚ na nacenime aj rozia (I-018); prístupy Financie k osobným údajom sa zapisujú do auditu.
  - Testy RLS: Financie nemôže meniť údaje náboru ani dokumenty, len stav financovania a svoje poznámky.
- [ ] **I-088** [S] Pilot s 1 maklérom (napr. Martin), úpravy, potom všetci.

### F8 · Modul Dokumenty (šablóny z duplikátu NajBývania → vyplnenie v CRM rozia)
*Rozhodnutie majiteľa (21. 11.):*
1. *Knižnica šablón (zmluvy, nájmy, dohody o spolupráci, formuláre) **pochádza z duplikátu CRM NajBývania**. CRM rozia ju odtiaľ preberá (len čítanie, rovnaký konektor ako F5).*
2. *V rozia je knižnica **len na stiahnutie / výber**. Do pôvodných šablón **nikto nezapisuje**, zostávajú prázdne a nevypísané.*
3. *Maklér vyberie šablónu a klienta a **CRM rozia vytvorí novú vyplnenú kópiu** s údajmi klienta a nehnuteľnosti z CRM. Šablóna sa tým nemení; vzniká samostatný dokument uložený pri klientovi.*

**Knižnica (zdroj = duplikát)**
- [ ] **I-089** 🔒 [C] V audite duplikátu (F2) zistiť, kde a ako má dokumenty: formát (podľa majiteľa prevažne **Word/DOCX**, niektoré možno PDF; staršie .doc treba previesť na DOCX), kategórie, verzie, či majú vyznačené polia na vyplnenie.
- [ ] **I-089a** 🔒 [C] Synchronizácia šablón duplikát → privátny Storage rozia (len čítanie, kontrolný súčet, zmena v duplikáte = nová verzia v rozia, stará ostáva v archíve).
- [ ] **I-089b** [C] Ochrana šablón: makléri majú k šablónam **len čítanie** (Storage politiky/RLS), nikto ich v rozia neprepíše ani nezmaže; overené testom aj cez API.
- [ ] **I-089c** [C] Obrazovka Knižnica: kategórie, vyhľadávanie, náhľad, „Stiahnuť prázdnu“ a „Vyplniť pre klienta“.

**Návrh dokumentov k náboru**
- [ ] **I-089d** 🔒 [S] Zoznamy dokumentov ku každej možnosti (byt, dom, pozemok, chata, prenájom, spoluvlastníctvo, špeciálne prípady) spíšeme **spolu v pondelok** podľa aktuálnej dokumentácie v duplikáte NajBývania. Základ: **Zmluva o sprostredkovaní predaja** pri každom nábore; byt → prepis vody, elektriny, plynu.
- [ ] **I-089e** [C] Pravidlá návrhu (`document_rules`): podmienka (typ, transakcia, vlastníctvo, pozemok, stavby…) → šablóna, **povinná** alebo **odporúčaná**. Spravuje admin bez programovania.
- [ ] **I-089f** [C] V nábore sekcia „Dokumenty“: povinné navrhnuté hore (Zmluva o sprostredkovaní vždy prvá), odporúčané pod nimi; maklér ich zaškrtne, odoberie alebo pridá iné z knižnice. Vybrané sa vyplnia údajmi náboru (I-090b).
- [ ] **I-089g** [C] Testy pravidiel: byt → zmluva + energie; každý nábor má zmluvu o sprostredkovaní; neznámy typ → aspoň zmluva o sprostredkovaní.

**Vyplnenie pre klienta (nový dokument, šablóna sa nemení)**
- [ ] **I-090** [S] Pre každú šablónu zoznam polí (meno, rodné číslo/dátum narodenia, adresa, LV, parcela, výmera, cena, provízia…) a ich mapovanie na polia CRM. [T] potvrdí, ktoré polia sa smú vypĺňať automaticky.
- [ ] **I-090a** [C] Ak šablóny z duplikátu nemajú vyznačené polia: pripraviť **odvodenú „vyplňovaciu“ verziu** so zástupnými poliami (napr. `{{klient.meno}}`). Originál z duplikátu zostáva nedotknutý; odvodená verzia sa viaže na verziu originálu a pri jeho zmene sa označí na kontrolu.
- [ ] **I-090b** [C] Generovanie: vybraná šablóna + klient/nehnuteľnosť → **nový súbor** (DOCX na úpravu + PDF na tlač). Šablóny len v PDF sa automaticky nevypĺňajú – ponúknu sa na stiahnutie prázdne, uložený na karte klienta. Údaje berie z CRM rozia (vrátane dát prenesených z duplikátu).
- [ ] **I-090c** [C] Chýbajúce údaje sa **nevymýšľajú**: pole bez hodnoty zostane viditeľne prázdne a maklér dostane zoznam, čo treba doplniť (do CRM alebo ručne do dokumentu).
- [ ] **I-090d** [C] Kontrola pred použitím: vyplnený dokument má stav „Návrh“, kým ho maklér neskontroluje; ku každému sa uloží verzia šablóny a čas vytvorenia.
- [ ] **I-090e** [C] Ručné doplnenie (rozhodnutie 21. 11.): maklér si vyplnený dokument **stiahne ako DOCX**, doplní voľné miesta sám a **nahrá späť** ku klientovi. Nahratý súbor sa uloží ako **nová verzia** toho istého dokumentu (predošlé verzie ostanú, nič sa neprepíše). Povolené formáty pri nahrávaní: DOCX a PDF, limit veľkosti, kontrola typu súboru. Pôvodná šablóna sa tým nikdy nemení.
- [ ] **I-091** [C] Prístupy: vyplnené dokumenty klienta vidí len jeho maklér a admin (RLS), sťahovanie cez podpísaný odkaz s krátkou platnosťou, nikdy verejná URL.
- [ ] **I-092** [C] Audit: kto vyplnil, kto stiahol, kto zmazal; GDPR výmaz klienta zmaže aj jeho vyplnené dokumenty.
- [ ] **I-093** [C] Testy: šablóna sa generovaním nezmení (kontrolný súčet pred/po), maklér nevidí dokumenty cudzích klientov, chýbajúce pole nespôsobí pád ani vymyslenú hodnotu.
- [ ] **I-093a** [T] Šablóny používané na vyplnenie nechať skontrolovať advokátom (najmä odvodené verzie s poľami).
- [ ] **I-093b** [C] Neskôr: elektronický podpis vyplneného dokumentu (samostatné rozhodnutie).

### F9 · Odhady a cenník (jeden zdroj čísel)
- [ ] **I-094** [C] Uložené nábory a predaje z CRM ako podklad pre cenník nacenime (export do `podklady.csv`, `zostav-cennik.php --doplnit`) – len €/m², lokalita, typ, bez osobných údajov.
- [ ] **I-095** [C] Predané nehnuteľnosti z CRM (`sales.price_per_m2`) ako spoločný zdroj pre cenník nacenime aj odhad na rozia – aby dva weby neukazovali rozdielne odhady.
- [ ] **I-096** [S] Rozhodnúť, či rozia.sk ponechá vlastnú kalkulačku ocenenia, alebo bude odkazovať / volať nacenime `/api/odhad`.

### F10 · Marketing (`nacenime-marketing`)
- [ ] **I-097** [S] Určiť obsah repa (kampane, landing pages, texty, reporty) a väzbu na CRM (zdroj leadu, UTM → pole `zdroj` v CRM).

### F11 · Prevádzka a ukončenie
- [ ] **I-098** [C] Runbook: čo robiť, keď nechodia leady, keď padne webhook, ako obnoviť zálohu (nacenime aj rozia).
- [ ] **I-099** [C] Monitoring: uptime pre `nacenime.sk/api/health`, `…/health/fronta`, endpoint príjmu v rozia; upozornenie, keď sú v `inbound_events` chybné udalosti.
- [ ] **I-100** [S] Po 2–4 týždňoch stabilného behu: contract fáza (odstrániť staré cesty, vypnúť e-mailové duplikáty, ak ich netreba), aktualizovať tento plán.

---

## 5. Časový rámec (orientačne)

| Kedy | Čo |
|---|---|
| **Sobota–nedeľa (21.–22. 11.)** | F0 a F1 (bez duplikátu), návrhy ADR v F3, ktoré nezávisia od duplikátu (I-034, I-035, I-036, I-038, I-039). |
| **Pondelok (23. 11.)** | F2 – audit duplikátu, rozhodnutie R1/R2. |
| **Týždeň 1** | F3 dokončenie, F4 základ rozia (staging). Paralelne F6 kroky I-069–I-076 (dajú sa na stagingu bez duplikátu). |
| **Týždeň 2–3** | F5 import (staging → produkcia), F6 ostrý beh po zverejnení upravených zásad ochrany OÚ (I-077). |
| **Týždeň 3+** | F7 Postup, F8 Dokumenty, F9, F11. |

## 6. Čo môže začať hneď (bez duplikátu a bez rozhodnutí)
I-003, I-008, I-011 až I-019, I-034 až I-039 (návrhy), I-069 až I-071 (podpis webhooku v nacenime za prepínačom, zatiaľ neaktívny).
