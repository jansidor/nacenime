# Zásady ochrany osobných údajov: podklad pre advokáta

**Toto nie je text na zverejnenie.** Je to prehľad toho, čo sa v systéme mení a čo treba v zásadách a v právnom posúdení doriešiť, než sa začnú prenášať leady z nacenime.sk do CRM rozia.sk (krok I-077 plánu). Návrhy znení sú pracovné, miesta v hranatých zátvorkách [DOPLNIŤ] musí doplniť firma alebo advokát. Texty na weboch sa týmto dokumentom nemenia.

Prevádzkovateľ na oboch weboch má byť ten istý: NajBývanie Ružomberok, s. r. o. (údaje firmy sú už v zásadách na nacenime.sk; na rozia.sk ešte chýbajú v `lib/site.ts`).

## 1. Čo sa mení oproti dnešnému textu na nacenime.sk (platnému od 14. 11. 2026)

| Oblasť | Dnes v zásadách | Nový stav v systéme | Čo to znamená pre text |
|---|---|---|---|
| Komu sa údaje sprístupňujú | „Maklérovi spoločnosti“ | Ocenenie s kontaktom ide do CRM rozia: pridelený maklér, dvaja admini a jedna osoba z finančného oddelenia (rola Financie) | doplniť okruh príjemcov a ich úlohy |
| Kde sa údaje uchovávajú | hosting nacenime (Hostcreators) | okrem toho databáza CRM v Supabase (región Frankfurt) a hosting aplikácie rozia | doplniť sprostredkovateľov, prenos mimo EÚ (pozri otázky) |
| Nedohodnutý nábor | údaje sa zmažú po 24 mesiacoch od zadania | po nedohode ostane meno, kontakt a popis nehnuteľnosti **24 mesiacov od uzavretia náboru**, potom sa anonymizujú; nábor sa dá v tom čase obnoviť, ak sa klient vráti | nový účel a doba uchovávania |
| Financovanie | nie je | Financie vidia meno, telefón a e-mail klienta pri všetkých začatých náboroch a môžu klienta kontaktovať kvôli financovaniu | nový účel, právny základ (pozri otázky) |
| Vrátený klient | nie je | nové ocenenie sa porovná s uloženým klientom podľa telefónu a e-mailu; pri zhode nehnuteľnosti sa obnoví predošlý nábor | informovať, že údaje sa môžu prepojiť s predošlým záznamom |
| Údaje z pôvodného CRM NajBývania | nie je | do CRM rozia sa priebežne prenášajú dáta z duplikátu pôvodného CRM (jednosmerne) | informačná povinnosť voči klientom, ktorí nikdy nepoužili nacenime (pozri otázky) |
| Výmaz | na žiadosť | výmaz v nacenime sa prenesie aj do CRM (udalosť o výmaze), údaje klienta sa anonymizujú; záznam o prístupe v audite ostáva | doplniť, že výmaz sa vzťahuje na oba systémy |
| Záznamy o prístupe | nie je | zapisuje sa, kto kedy zobrazil údaje klienta (najmä rola Financie), a zmeny náborov | informovať o kontrolnom zázname |

## 2. Pracovné návrhy znení (na úpravu advokátom)

**Účely a základy (doplnenie tabuľky):**
- *Spracovanie dopytu v CRM:* meno, telefón, e-mail, údaje a odhad nehnuteľnosti, pridelenie maklérovi; základ: vaša žiadosť o kontakt (čl. 6 ods. 1 písm. b GDPR), pri PDF so súhlasom váš súhlas (písm. a). [DOPLNIŤ po posúdení]
- *Uchovanie kontaktu po nedohode o spolupráci:* meno a priezvisko, telefón, e-mail, stručný popis nehnuteľnosti, dôvod uzavretia; základ: oprávnený záujem (písm. f) na obnovení spolupráce, ak sa vrátite; doba **24 mesiacov**, potom anonymizácia; môžete namietať. [POSÚDIŤ, pozri otázku 3]
- *Sprostredkovanie financovania:* meno a kontakt, údaje o nehnuteľnosti a priebeh financovania; základ: [DOPLNIŤ: súhlas alebo plnenie zmluvy, pozri otázku 2].

**Príjemcovia (doplnenie):** pridelený maklér, administrátori CRM a pracovník finančného oddelenia spoločnosti NajBývanie Ružomberok, s. r. o. [DOPLNIŤ], sprostredkovatelia: Supabase (databáza CRM, región Frankfurt), [hosting rozia.sk: DOPLNIŤ], Hostcreators (nacenime.sk).

**Doba uchovávania (doplnenie):** údaje klienta, ktorý spoluprácu neuzavrel, ostávajú 24 mesiacov od uzavretia náboru, potom sa anonymizujú. Údaje klienta, s ktorým spolupráca prebieha alebo skončila predajom, [DOPLNIŤ: doba podľa zákonných povinností].

**Práva:** výmaz a odvolanie súhlasu sa premietnu do oboch systémov do [DOPLNIŤ] dní.

## 3. Otázky pre advokáta
1. **Spoločný prevádzkovateľ:** potvrdiť, že nacenime.sk, rozia.sk a CRM sú jeden prevádzkovateľ (jedna firma), a že prenos medzi nimi je interný, nie poskytnutie tretej strane.
2. **Financovanie:** stačí informovanie klienta, alebo treba **súhlas** s tým, aby jeho kontakt dostalo finančné oddelenie a ozvalo sa mu? V systéme je pripravený príznak `gdpr_purpose_financing` pri klientovi (zatiaľ sa nenastavuje). Ak treba súhlas, kedy sa pýta (pri založení náboru) a ako sa dokladá.
3. **Archív nedohodnutých:** je oprávnený záujem (24 mesiacov) obhájiteľný, alebo treba súhlas s uchovaním kontaktu po nedohode? Je 24 mesiacov primerané?
4. **Údaje z pôvodného CRM NajBývania:** má sa klientom, ktorých údaje sa preberajú z duplikátu, oznámiť zmena (informačná povinnosť čl. 13/14), a ako? Aký je pôvodný účel a súhlas v pôvodnom CRM a je zlučiteľný s používaním v rozia?
5. **Prenos mimo EÚ:** hosting aplikácie rozia a sprostredkovatelia môžu byť americké spoločnosti; potrebné zmluvy o spracúvaní (DPA), štandardné zmluvné doložky alebo rámec EU-US DPF. Ktoré treba uzavrieť?
6. **Zhoda klienta („vrátený klient“):** automatické porovnanie podľa telefónu a e-mailu je profilovanie v zmysle GDPR? Treba o ňom informovať osobitne?
7. **Rola Financie:** primeranosť rozsahu (meno, telefón, e-mail všetkých začatých náborov vopred), minimalizácia, a či sa má jej prístup obmedziť na klientov, ktorí o financovanie prejavili záujem.
8. **Záznamy o spracúvaní (čl. 30) a posúdenie vplyvu:** treba aktualizovať záznamy; je potrebné posúdenie vplyvu?
9. **Výmaz a záloha:** ako dlho môžu osobné údaje ostať v zálohách po výmaze, a ako to uviesť v zásadách?
10. **Text súhlasu na rozia.sk:** dnešný text formulára je stručný („Súhlasím so spracovaním údajov na účel ocenenia a kontaktovania.“) a odkazuje len všeobecne. Má sa zosúladiť s textom na nacenime.sk a pridať odkaz na zásady.

## 4. Technické fakty o systéme (pre posúdenie)
- **Kto čo vidí (vynucuje databáza):** maklér len svoje ocenenia, nábory a klientov; admin všetko; Financie nábory všetkých pobočiek a kontakt klienta len pri klientovi, ktorý má nábor, a každý takýto prístup sa zapíše do auditu.
- **Nepridelené ocenenia** (bez pobočky) vidí a môže si vziať ktorýkoľvek maklér.
- **Odovzdanie** náboru kolegovi (aj z inej pobočky) prenesie klienta, poznámky a dokumenty; zapíše sa do histórie.
- **Archív:** nedohodnutý nábor ostane s menom, kontaktom a popisom nehnuteľnosti; rozpracované dokumenty sa zmažú. Po 24 mesiacoch sa nábor aj klient anonymizujú (meno, telefón, e-mail, adresa, parcela, číslo LV, poznámky, dokumenty), ak klient nemá iný aktívny nábor.
- **Webhook nacenime → rozia:** podpísaný (HMAC-SHA256), opakované doručenie nevytvorí duplikát; ide len ocenenie, pri ktorom je telefón alebo súhlas.
- **Umiestnenie dát:** CRM v Supabase (Frankfurt), nacenime na hostingu Hostcreators; [hosting aplikácie rozia: DOPLNIŤ].
- **Nasadenie:** webhook zostáva vypnutý, kým sa zásady nezverejnia (krok I-077 a I-078 plánu).

## 5. Kontrola pred zapnutím prenosu leadov
- [ ] Advokát posúdil otázky 1 až 10.
- [ ] Zásady na nacenime.sk aj rozia.sk uvádzajú rovnakého prevádzkovateľa, nové účely, príjemcov a doby uchovávania.
- [ ] Údaje firmy sú doplnené v `lib/site.ts` na rozia.sk.
- [ ] Text súhlasu vo formulári rozia.sk je zosúladený.
- [ ] Zmluvy so sprostredkovateľmi sú uzavreté.
- [ ] Záznamy o spracúvaní sú aktualizované.
- [ ] Až potom majiteľ zapne `NACENIME_LEAD_WEBHOOK` a tajomstvo (krok I-078).
