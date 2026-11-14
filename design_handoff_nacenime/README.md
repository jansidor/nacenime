# Handoff: nacenime.sk – kalkulačka ceny nehnuteľnosti

## Prehľad
Web, na ktorom majiteľ zadá adresu, odpovie na 5 otázok (plus jednu nepovinnú) a hneď vidí orientačné cenové rozpätie. Kontakt sa pýta až na výsledku a je dobrovoľný. Regióny: Liptov, Turiec, Orava.

## O súboroch
`Nacenime.dc.html` je **dizajnová referencia v HTML**, nie produkčný kód. Úlohou je obrazovky postaviť nanovo v cieľovom prostredí (ak ešte žiadne nie je, zvoľte vhodný framework so serverovou časťou, lebo adresa potrebuje vlastný endpoint). Prototyp beží na vzorových dátach: našepkávač, mapa, výpočet ceny ani odosielanie formulárov nie sú napojené.

Prototyp sa otvára priamo v prehliadači (`support.js` musí ležať vedľa). Obrazovky a stavy sa prepínajú cez props `startScreen`, `showSuggest`, `googleDown`, `outOfRegion`, `autoAdvance`.

## Vernosť
**Hi-fi.** Farby, písmo, veľkosti a rozostupy sú finálne. Texty v hranatých zátvorkách sú zástupné a treba ich nahradiť dátami.

## Dizajnové hodnoty (aktuálna vizuálna vrstva, nahrádza staršie hodnoty)

**Farby** (bez ďalších, bez tieňov, bez tmavého režimu, bez prechodov farieb)
- `#f6f4ef` pozadie stránky, text v pätičke
- `#ffffff` polia, možnosti, karty, našepkávač
- `#11122f` text, pätička, hover odkazov
- `#272361` nadpisy, logo, rámy, odkazy, vybraná možnosť, špendlík, vyplnený úsek stupnice
- `#d32441` jediné červené (hlavné) tlačidlo na obrazovke, hover `#b81d37`, biely text 600
- `#dcd9d0` tiché plochy: mapa, fotka, prázdna časť pruhu a stupnice
- `#50516a` placeholder v poliach

**Písmo:** jedna rodina, Archivo 400 / 600 / 800 (Google Fonts, subset latin-ext). Space Grotesk sa nepoužíva.
- Stupnica: 16 / 19 / 24 / 32 / 44 / 64 px. Základ 18 px na mobile, 19 px od šírky 600 px. Nič pod 16 px.
- Nadpisy: 800, riadkovanie 1.05, letter-spacing −0.02em, veta s malými písmenami. Text: 400, riadkovanie 1.5, zvýraznenie 600.
- H1 úvod `clamp(44px, 8vw, 64px)`, max 16ch; H1 otázka a mesto `clamp(32px, 5vw, 44px)`; H2 24 px / 32 px.
- Cena na výsledku: 800, `clamp(min(48px, 17vw), 10vw, 120px)`, tabuľkové číslice, tisíce a „€“ oddelené pevnou medzerou (131 000 €). Pri šírke pod 282 px sa zmenšuje, aby sa pri zväčšení písma na 200 % zalomila a neorezala.
- Riadok textu max 62 znakov (`max-width: 62ch`).
- Jediná ručná medzera pri „ľ“: v slove „Koľko“ (nadpis úvodu a stránky mesta) má „ľ“ `margin-right: 0.1em`, lebo sa mäkčeň dotýkal „k“. „U vás zatiaľ neoceňujeme“ je bez úpravy.

**Tvary a rozmery**
- Polomer 12 px: polia, tlačidlá, možnosti, pruh postupu, stupnica. Polomer 20 px: mapa a fotka.
- Pole: výška 60 px, rám 1.5 px `#272361`, biele pozadie. Tlačidlá a možnosti min. výška 60 px, medzery aspoň 12 px.
- Zameranie: obrys 3 px `#272361`, odstup 2 px (v pätičke `#f6f4ef`).
- Každé pole má viditeľný popis (label) nad sebou; v poli je len príklad („Bystrická cesta 12, Ružomberok“). Výmera a cena majú jednotku (m², €) stále v poli; `inputmode="numeric"`.
- Chyba: veta pod poľom s ikonou (kruh s výkričníkom), nikdy len farba.
- Obsah: max 1060 px, bočný okraj `clamp(20px, 5vw, 64px)`. Kalkulačka a „Mimo regiónu“: max 640 px; formulár adresy 760 px. Všetko zarovnané vľavo.
- Jediná deliaca čiara: 1.5 px `#272361` nad kontaktnými blokmi vo výsledku.
- Pohyb: len prechod medzi obrazovkami 150 ms (fade); pri `prefers-reduced-motion` žiadny.

**Tlačidlá**
- Červené (hlavné): `#d32441`, biely text 600, hover `#b81d37`. Na obrazovke vždy len jedno.
- Vedľajšie: biele s rámom 1.5 px `#272361`, text `#272361`; hover plné `#272361` s bielym textom.
- Možnosť v kalkulačke: biela s rámom; hover a vybraná = plná `#272361` s bielym textom, potom sa ide ďalej.
- Odkazy: `#272361`, podčiarknuté (odsadenie 4 px).

## Zmeny oproti pôvodnému návrhu
- Kalkulačka: tlačidlo „Pokračovať“ je červené (predtým tmavé); „Mimo regiónu“: „Dajte mi vedieť“ je červené (predtým obrysové).
- Výsledok: blok „Cena od makléra“ je prvý (hlavný, červený), „Odhad v PDF“ druhý (vedľajší). Poradie v DOM zodpovedá poradiu Tabu.
- Pätička: „Prevádzkuje NajBývanie, s.r.o.“
- Úvod: tri otázky ostávajú ako tichý text bez kariet a ikon. Hlavička je len logo.

## Nové hodnoty v logike (`renderVals`)
- `hasPdfMsg`: `true`, keď `pdfMsg` nie je prázdne (po odoslaní formulára Odhad v PDF).
- `pdfIsErr`: `true`, keď `pdfMsg` začína „Zadajte“; `false` pri potvrdení. Riadi ikonu chyby.
- `hasCallMsg`: `true`, keď `callMsg` nie je prázdne (po odoslaní „Zavolajte mi“).
- `callIsErr`: `true`, keď `callMsg` začína „Zadajte“.
- `hasNotifyMsg`: `true`, keď `notifyMsg` nie je prázdne (obrazovka Mimo regiónu).
- `notifyIsErr`: `true`, keď `notifyMsg` začína „Zadajte“.
- `priceText`: pred „€“ je pevná medzera (`\u00a0`).
- Počiatočná obrazovka: `?screen=` v adrese, potom prop `startScreen`, potom „Úvod". Len pre `Nacenime Prehlad.dc.html`; v produkcii nepotrebné.

## Tok
Úvod (alebo Mesto) → Potvrdenie adresy → Kalkulačka (5 otázok + 1 nepovinná → „Máme všetko“) → Výsledok.

Odbočky:
- „Oceňujete pozemok? Pokračujte bez adresy.“ → rovno otázka 1.
- Záloha bez Google → rovno otázka 1 (bez mapy, lebo nie sú súradnice).
- Adresa alebo obec mimo regiónu → obrazovka „Mimo regiónu“, nie kalkulačka.
- „Späť“ na otázke 1 vracia tam, odkiaľ používateľ prišiel.
- Logo vždy vedie na Úvod.

## Obrazovky

### 1. Úvod
- H1, podnadpis, formulár adresy, riadok s regiónmi, odkazy na 5 miest, tri otázky s odpoveďami (grid `auto-fit, minmax(240px, 1fr)`, medzery 28 × 48 px).
- Formulár: jedno pole a červené tlačidlo v spoločnom ráme 2 px, biele pozadie. Pole pri fokuse `#f6f4ef`.
- Našepkávač: priamo pod formulárom, rovnaká šírka, rám 2 px bez horného. Riadok min. 60 px: ulica a číslo 20 px / 700 `#272361`, pod tým obec 16 px. Hover riadku `#f6f4ef`.
- Záloha: dve samostatné polia s rámom („Ulica a číslo“, „Obec“) a červené tlačidlo, medzera 12 px. Pod nimi prípadná veta o regióne (700) a odkaz pre pozemok.

### 2. Potvrdenie adresy
- Mapa na celú šírku obsahu, výška 240 px, zaoblenie 24 px. Špendlík `#272361` so svetlým stredom, ťahateľný. Vpravo dole dve tlačidlá 44 × 44 px (+ a −), biele s rámom.
- H1 s adresou vo formáte „Ulica číslo, PSČ Obec“.
- Ak je adresa mimo regiónu: veta 700 „Zatiaľ oceňujeme len Liptov, Turiec a Oravu.“ pod nadpisom.
- Otázka, dve tlačidlá vedľa seba (obrysové „Upraviť“ užšie, červené „Áno, pokračovať“ širšie, spolu max. 560 px), pod nimi odkaz pre pozemok.

### 3. Mimo regiónu
- H1, veta, pole E-mail a obrysové tlačidlo v jednom riadku, stavová správa, odkaz „Späť na úvod“.

### 4. Kalkulačka
- Pruh postupu, riadok s počítadlom a odkazom „Späť“, H1 s otázkou, pod ňou odpovede.
- Otázky 1, 2, 4, 5: zoznam možností pod sebou (min. výška 60 px, medzera 12 px). Klik vyberie a posunie ďalej.
- Otázka 3: pole s jednotkou „m²“ a tmavé „Pokračovať“. Platná hodnota 10 až 2000, akceptuje desatinnú čiarku.
- Nepovinná otázka: pole s „€“, tmavé „Pokračovať“, odkaz „Preskočiť“. Počítadlo ukazuje „Nepovinná otázka“, pruh je plný. Bez validácie.
- Koniec: „Hotovo“ / „Máme všetko“ a červené „Zobraziť odhad“.

### 5. Výsledok
- Zhrnutie (adresa, typ, výmera), H1 s cenovým rozpätím, stupnica (pás 28 px `#dcd9d0`, v ňom úsek `#272361`, pod ním krajné hodnoty), veta o počte predajov.
- Pod čiarou 2 px dva stĺpce: „Odhad v PDF“ (e-mail, nepovinný súhlas, obrysové tlačidlo) a „Cena od makléra“ (telefón, červené tlačidlo, veta o maklérovi).
- Dole poznámka a odkaz „Upraviť údaje“ (späť na otázku 1).

### 6. Mesto (landing pre každé mesto)
- Dva stĺpce: vľavo H1 s názvom mesta v lokáli, podnadpis, formulár adresy; vpravo fotka (zaoblenie 24 px, `min-height: clamp(240px, 38vw, 480px)`).
- Sekcia „Ceny v…“: tri čísla, odsek o štvrtiach, riadok „Naposledy predané“.
- V pätičke navyše odkazy na ostatné mestá.
- Mestá: Ružomberok, Liptovský Mikuláš, Martin, Dolný Kubín, Námestovo.

## Adresa a mapa (zadanie)

**Našepkávač: Google Places API (New), Autocomplete**
- Volať cez vlastný serverový endpoint (napr. `/api/adresa?q=...`), nie priamo z prehliadača. API kľúč len na serveri v `.env`, nikdy v kóde ani v repozitári.
- Parametre: `regionCode=sk`, `languageCode=sk`, `includedPrimaryTypes` = `route`, `street_address`, `premise`, `subpremise`.
- Hľadať až od 3 znakov, s oneskorením 300 ms po poslednom stlačení klávesu.
- Session token: jedna relácia = písanie + jeden výber adresy, ukončená volaním Place Details (stačí úroveň Essentials: adresa, PSČ, súradnice).
- Výsledky obmedziť na okresy Ružomberok, Liptovský Mikuláš, Martin, Turčianske Teplice, Dolný Kubín, Námestovo, Tvrdošín.
- Adresu mimo nich prijať, ukázať vetu „Zatiaľ oceňujeme len Liptov, Turiec a Oravu.“ a po potvrdení poslať na obrazovku „Mimo regiónu“.

**Záloha bez Google (povinná)**
- Ak Google neodpovie alebo vráti chybu, formulár sa nesmie zablokovať. Zobraziť polia „Ulica a číslo“ a „Obec“ a pustiť ďalej.
- Pole „Obec“ porovnať so zoznamom obcí v 7 okresoch (bez ohľadu na diakritiku a veľkosť písmen). V prototype je len vzorový zoznam 10 obcí; treba dodať úplný.
- Obec mimo zoznamu: veta pod poľami po opustení poľa; pri odoslaní obrazovka „Mimo regiónu“.
- Odkaz „Oceňujete pozemok? Pokračujte bez adresy.“

**Potvrdenie adresy: Maps JavaScript API**
- Jedna mapa so špendlíkom na súradniciach z Place Details. Špendlík sa dá posunúť ťahaním; uložiť výsledné súradnice.
- Sivý štýl mapy, žiadne ovládacie prvky okrem priblíženia.
- Mapu načítať až na tejto obrazovke, nie na úvode.

**Kľúč**
- Obmedzený na domény `nacenime.sk` a `*.nacenime.sk`. Pre lokálny vývoj a testovaciu doménu treba pridať ďalšie adresy v Google Cloud (projekt `nacenime-sk`, Keys & Credentials).

## Stavy obrazoviek
Označenie: **(P)** je v prototype, **(N)** nie je nakreslené a treba ho doplniť v rovnakom štýle (text 700, bez ikon a farebných plôch).

**Úvod / Mesto – pole adresy**
- Prázdne: placeholder „Adresa nehnuteľnosti“, bez návrhov. (P)
- 1–2 znaky: nič sa nehľadá. (P)
- Načítava sa: čakanie na odpoveď endpointu. (N) Návrh: zoznam sa neukáže, kým nepríde odpoveď; žiadny spinner.
- Návrhy: 1 až 5 riadkov. (P)
- Žiadny výsledok: (N) prototyp vtedy ukáže vzorové adresy. Text treba doplniť.
- Chyba alebo timeout Google: prepnúť na zálohu s dvoma poľami. (P)
- Záloha, obec mimo regiónu: veta pod poľami. (P)
- Odoslanie bez výberu návrhu: prototyp vezme prvý návrh. Odoslanie úplne prázdneho poľa nie je ošetrené. (N)

**Potvrdenie adresy**
- Načítava sa mapa: sivá plocha `#dcd9d0` v rozmere mapy. (P, ako zástupná plocha)
- Mapa sa nenačíta: (N) nechať sivú plochu, adresa a tlačidlá fungujú ďalej.
- Adresa v regióne: bez vety. (P)
- Adresa mimo regiónu: s vetou; „Áno, pokračovať“ vedie na „Mimo regiónu“. (P)
- Špendlík posunutý: bez vizuálnej zmeny, ukladajú sa súradnice. (P)

**Mimo regiónu**
- Prázdne: pole s placeholderom. (P)
- Chyba: „Zadajte e-mail.“ (P)
- Odoslané: „Ďakujeme, dáme vám vedieť.“ (P)
- Odosiela sa / chyba servera: (N)

**Kalkulačka**
- Otázka bez odpovede / s vybranou odpoveďou (pri návrate späť). (P)
- Výmera prázdna alebo mimo 10–2000: „Zadajte výmeru od 10 do 2000 m².“ (P)
- Nepovinná otázka prázdna: dá sa pokračovať aj preskočiť. (P)
- Hotovo: „Máme všetko“. (P)

**Výsledok**
- Načítava sa odhad: (N)
- Odhad sa nedá vypočítať (málo predajov v okolí) alebo chyba servera: (N)
- Zobrazený odhad. (P)
- PDF: prázdne / „Zadajte e-mail.“ / „Odhad vám pošleme na e-mail.“ (P); odosiela sa, chyba servera (N)
- Maklér: prázdne / „Zadajte telefónne číslo.“ (menej ako 9 číslic) / „Ďakujeme, ozveme sa vám.“ (P); odosiela sa, chyba servera (N)

**Mesto**
- S fotkou (Ružomberok, Liptovský Mikuláš) / bez fotky: sivá plocha so zástupným textom. (P)
- Ceny a text: zatiaľ zástupné hodnoty. Stav bez dát pre mesto: (N)

## Texty

**Spoločné**
- Logo: nacenime.sk
- Pätička: Prevádzkuje NajBývanie, s.r.o.
- Odkazy v pätičke: Ochrana osobných údajov · Cookies · Kontakt

**Úvod**
- Koľko stojí váš byt alebo dom?
- Päť otázok a cenu vidíte hneď. Bez registrácie, bez telefónu.
- Pole: Adresa nehnuteľnosti
- Tlačidlo: Zistiť cenu
- Liptov, Turiec, Orava. Orientačný odhad, nie znalecký posudok.
- Mestá: Ružomberok · Liptovský Mikuláš · Martin · Dolný Kubín · Námestovo
- Musím zadať kontakt? / Nie. E-mail len vtedy, ak chcete odhad v PDF.
- Z čoho cenu rátate? / Z predajov vo vašom okolí.
- Bude mi niekto volať? / Iba ak o to požiadate.
- Záloha: Ulica a číslo · Obec · Zistiť cenu
- Zatiaľ oceňujeme len Liptov, Turiec a Oravu.
- Oceňujete pozemok? Pokračujte bez adresy.

**Potvrdenie adresy**
- [MAPA]
- Vzorová ulica 1, 034 01 Ružomberok (vzorový údaj)
- Zatiaľ oceňujeme len Liptov, Turiec a Oravu.
- Je to správna adresa?
- Upraviť · Áno, pokračovať
- Oceňujete pozemok? Pokračujte bez adresy.
- Popisy pre čítačky: Priblížiť · Oddialiť · Špendlík, dá sa posunúť ťahaním

**Mimo regiónu**
- U vás zatiaľ neoceňujeme
- Dáme vám vedieť, keď začneme.
- Pole: E-mail
- Tlačidlo: Dajte mi vedieť
- Zadajte e-mail. / Ďakujeme, dáme vám vedieť.
- Späť na úvod

**Kalkulačka**
- Otázka 1 z 5 … Otázka 5 z 5 · Nepovinná otázka · Hotovo
- Späť
- Prečo zisťujete cenu? Len zisťujem · Predám do 3–6 mesiacov · Dedičstvo · Rozvod · Investícia
- Čo oceňujeme? Byt · Dom · Chata · Apartmán · Pozemok
- Aká je výmera? [pole] m² · Pokračovať · Zadajte výmeru od 10 do 2000 m².
- V akom je stave? Pôvodný · Čiastočná rekonštrukcia · Kompletná rekonštrukcia · Novostavba
- Má balkón, loggiu alebo terasu? Áno · Nie
- Akú máte predstavu o cene? [pole] € · Pokračovať · Preskočiť
- Máme všetko · Zobraziť odhad

**Výsledok**
- Zhrnutie: Vzorová ulica 1, Ružomberok. Byt 66 m² (vzorový údaj)
- 120 až 135 tisíc € (vzorový údaj; pod stupnicou 120 a 135)
- Vyššiu cenu dosahujú byty po rekonštrukcii a s dobrou prípravou predaja.
- Veta o zdroji, variant a): Podľa [X] podobných predajov v okolí.
- Veta o zdroji, variant b): Podľa [X] aktuálnych ponúk v okolí, po odpočítaní bežnej zľavy pri predaji. (prepína prop `sourceText`; treba vybrať jeden podľa zdroja dát)
- Odhad v PDF · Pole: E-mail · Súhlasím, aby ma maklér NajBývanie kontaktoval ohľadom predaja. Nepovinné. · Poslať PDF
- Zadajte e-mail. / Odhad vám pošleme na e-mail.
- Cena od makléra · Pole: Telefón · Zavolajte mi
- [MENO MAKLÉRA] si nehnuteľnosť pozrie a povie jednu cenu.
- Zadajte telefónne číslo. / Ďakujeme, ozveme sa vám.
- Orientačný, nezáväzný odhad. Nejde o znalecký posudok.
- Upraviť údaje

**Mesto**
- Koľko stojí váš byt alebo dom v Ružomberku? (v Liptovskom Mikuláši / v Martine / v Dolnom Kubíne / v Námestove)
- Päť otázok a cenu vidíte hneď. Bez registrácie, bez telefónu.
- Adresa nehnuteľnosti · Zistiť cenu
- Orientačný odhad, nie znalecký posudok.
- Ceny v Ružomberku (a ostatné lokály)
- [CENA ZA M² BYTY] priemerná cena bytu za m²
- [CENA ZA M² DOMY] priemerná cena domu za m²
- [POČET PREDAJOV] predajov, z ktorých rátame
- [TEXT O ŠTVRTIACH A CENÁCH]
- Naposledy predané: [TYP, VÝMERA, ŠTVRŤ, CENA ZA M²]
- [FOTKA MARTINA] · [FOTKA DOLNÉHO KUBÍNA] · [FOTKA NÁMESTOVA]

## Dáta, ktoré treba ukladať
- Adresa: text, PSČ, súradnice (po prípadnom posunutí špendlíka), príznak „bez adresy“ a „zo zálohy“
- Odpovede: dôvod, typ, výmera, stav, balkón/loggia/terasa, predstava o cene (nepovinná)
- Výsledok: dolná a horná hranica, počet podobných predajov
- Kontakty: e-mail pre PDF + súhlas s kontaktovaním (nepovinný, predvolene nezaškrtnutý), telefón pre makléra, e-mail z „Mimo regiónu“

## Otvorené body
- Výpočet ceny: pozri „Výpočet odhadu“ nižšie.
- Chýbajú fotky Martina, Dolného Kubína a Námestova, meno makléra a texty o mestách.
- Stavy označené (N) treba dotiahnuť.
- Texty pre stránky Ochrana osobných údajov, Cookies a Kontakt nie sú navrhnuté.

## Súbory
- `Nacenime.dc.html` – prototyp všetkých obrazoviek
- `Nacenime Prehlad.dc.html` – úvod, otázka a výsledok vedľa seba v šírke 360 a 1280 px (iframe-y na `Nacenime.dc.html?screen=…`)
- `support.js` – runtime prototypu (netreba prenášať do produkcie)
- `img/ruzomberok.jpg`, `img/liptovsky-mikulas.jpg` – fotky miest

## Výpočet odhadu

**Produkcia:** výpočet beží na serveri v PHP, endpoint `POST /api/odhad`. Cenník (`nacenime-cennik.json`) leží mimo verejného priečinka, server ho číta raz pri štarte. Do prehliadača ide len výsledok, nikdy celý cenník. Prahy a zľavy sa čítajú zo súboru, nie z kódu.

**Prototyp:** `Nacenime.dc.html` počíta rozpätie priamo v prehliadači z `data/nacenime-cennik.json`, aby sa dala obrazovka Výsledok preklikať. Je to len ukážka, do produkcie sa nepreberá.

**Referencia na prepis do PHP:** `reference/odhad.js` (pravidlá) a `reference/odhad.test.js` (testy, spustíš `node --test reference/`). PHP verzia musí dať rovnaké výsledky ako testy.

Vstup: `typ`, `obec`, `stav` (pri pozemku sa neposiela), `vymera`. Výstup: `stav` (`ok`, `málo dát`, `mimo regiónu`, `maklér`), `od`, `do`, `pocet`, `zdroj`, `uroven`, `pouzity_stav`, `veta`, `nadpis`.

Postup: okres z `lokality[obec]` (obec sa porovnáva bez diakritiky a veľkosti písmen). Prvá skupina s `pocet` aspoň podľa prahu v poradí: Predaje obec stav, Ponuky obec stav, Predaje okres stav, Ponuky okres stav, potom to isté so stavom „všetky“. Predaje: od = q1 × výmera, do = q3 × výmera. Ponuky: od = q1 × výmera − najväčšia zľava, do = q3 × výmera − najmenšia zľava. Obe na tisíce, nie pod nulu. Cenu nič nenavyšuje.

**Obrazovka Výsledok**
- `ok`: H1 „131 000 až 145 000 €“, ilustračná stupnica, veta podľa typu, veta o zdroji, PDF aj maklér.
- Stupnica je ilustrácia, nie graf z dát: šírka = do − od, pás od (od − šírka) po (do + šírka), tmavý úsek je stredná tretina, pod jeho koncami „od“ a „do“ v tisícoch.
- `málo dát`: nadpis „Na odhad tu máme málo predajov“, veta „Maklér vám povie cenu po obhliadke.“, len blok „Cena od makléra“.
- `maklér` (typ vypnutý prepínačom, zoznam typov je v konfigurácii, predvolene prázdny): nadpis „Cenu vám povie maklér“, veta „Pri tomto type nehnuteľnosti rozpätie neukazujeme. Maklér si ju pozrie a povie vám cenu.“, len blok „Cena od makléra“, bez stupnice a bez PDF.
- `mimo regiónu`: obrazovka „Mimo regiónu“.
- Veta pod stupnicou: „Vyššiu cenu dosahujú [byty / apartmány / domy / chaty] po rekonštrukcii a s dobrou prípravou predaja.“ Pozemok: „Vyššiu cenu dosahujú pozemky so sieťami a prístupovou cestou.“
- Pozemok preskakuje otázku na stav. Ak adresa chýba, kalkulačka sa na konci spýta na obec.
- Meno makléra je jedna hodnota v konfigurácii (`maklerName`), zatiaľ „[MENO MAKLÉRA]“.
