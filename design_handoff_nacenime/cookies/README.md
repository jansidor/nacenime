# Cookie súhlas

Cookie lišta a načítanie sledovania **až po súhlase**. Jediný súbor na zapojenie: `cookies.js`, do stránky sa vkladá jedným riadkom v `<head>`:

```html
<script src="./cookies/cookies.js" defer></script>
```

## Čo robí
- Pri prvej návšteve ukáže lištu. Pred rozhodnutím sa **nenačíta nič**: ani Google Tag Manager, ani Microsoft Clarity.
- **Prijať všetko** a **Odmietnuť** majú rovnaký vzhľad aj dôraz. **Nastavenia** dovolia vybrať kategórie (analytické sú predvolene vypnuté, nevyhnutné nejdú vypnúť).
- Voľba sa uloží do nevyhnutnej cookie `nm_cookies` na 6 mesiacov (potom sa lišta ukáže znova; zvýšením `VERZIA` v súbore sa dá súhlas vypýtať znova pri zmene účelov).
- Po analytickom súhlase sa najprv nastaví **Consent Mode v2** (všetko zamietnuté, povolená len `analytics_storage`) a až potom sa načíta GTM a Clarity.
- Súhlas sa dá **odvolať** odkazom „Cookies“ v pätičke (alebo `window.NacenimeCookies.otvor()`, alebo prvkom s `data-nm-cookies`). Odvolanie zmaže analytické cookies a stránku načíta znova.
- Esc v nastaveniach nie je rozhodnutie. V iframe (náhľady dizajnu) sa lišta ani sledovanie nespúšťa.

## Nastavenie
Na začiatku `cookies.js`: `GTM_ID` (`GTM-WHXQF3H7`) a `CLARITY_ID` (`zbymfki8xh`). Nepotrebné ID nechaj prázdne.

## Poznámky
- Microsoft Clarity bol predtým v `Nacenime.dc.html` načítaný **bez súhlasu**. Teraz sa načíta len po analytickom súhlase.
- V **Google Tag Manageri** treba pridať značku GA4 s ID merania (`G-…`) a kontajner publikovať. Kód z GTM sa už nemá vkladať do stránky ručne, rieši ho tento modul.
- **Texty lišty a nastavení sú návrh a treba ich právne skontrolovať.** Chýba stránka „Zásady cookies / Ochrana osobných údajov“ (odkazy v pätičke zatiaľ nikam nevedú), na ktorú by lišta mala odkazovať.
- Vzhľad podľa `design_handoff_nacenime/README.md` (farby, 60 px tlačidlá, polomer 12 px, bez tieňov, text nie je pod 16 px). Žiadne červené tlačidlo, aby na obrazovke ostalo jediné červené.

## Test
```bash
node design_handoff_nacenime/cookies/test.cjs     # potrebuje Playwright a npm (stiahne React/Babel pre prototyp)
```
Externé požiadavky sa v teste nahrádzajú prázdnymi odpoveďami, nič nejde do siete.
