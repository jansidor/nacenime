# Meranie: udalosti pre GA4 cez Google Tag Manager

Kalkulačka posiela do `dataLayer` udalosti **len po súhlase s analytikou** (cookie lišta, `cookies/cookies.js`). Bez súhlasu `dataLayer` neexistuje a nič sa neposiela. Udalosti neobsahujú osobné údaje (e-mail, telefón, adresu).

| `event` | Kedy | Parametre |
|---|---|---|
| `odhad` | zobrazí sa výsledok (aj „málo dát“ alebo „maklér“) | `typ` (Byt, Dom, …), `vysledok` (`ok`, `málo dát`, `maklér`) |
| `pdf` | úspešne odoslaná žiadosť o PDF | `typ`, `suhlas` (`true`, ak súhlasil s kontaktovaním maklérom) |
| `telefonat` | úspešne odoslaná žiadosť „Zavolajte mi“ | `typ` |
| `mimo_regionu` | adresa alebo obec mimo Liptova, Turca a Oravy | `typ` (ak je známy) |
| `mimo_regionu_email` | odoslaný e-mail na obrazovke Mimo regiónu | `typ` |

## Nastavenie v GTM (kontajner `GTM-WHXQF3H7`)
1. **Premenné** → Používateľom definované → Premenná dátovej vrstvy: `typ`, `vysledok`, `suhlas` (názvy rovnaké ako parametre).
2. **Spúšťač** → Vlastná udalosť, názov udalosti `odhad|pdf|telefonat|mimo_regionu|mimo_regionu_email`, zaškrtnúť „Použiť zhodu regulárneho výrazu“.
3. **Značka** → Google Analytics: Udalosť GA4, ID merania `G-X0825SJMQ2`, názov udalosti `{{Event}}`, parametre `typ`, `vysledok`, `suhlas` z premenných. Spúšťač z bodu 2.
4. **Náhľad** (Preview) na `https://nacenime.sk`: prijať cookies, prejsť kalkulačku, overiť, že sa značka spustila. Potom **Odoslať / Publikovať**.
5. V **GA4** → Správca → Udalosti: označiť `pdf` a `telefonat` (prípadne aj `odhad`) ako **kľúčové udalosti** (konverzie).

Test: `node frontend/napojenie/test.cjs` overuje, že bez súhlasu sa nič neposiela, a so súhlasom presné udalosti bez osobných údajov.
