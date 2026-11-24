# Webhook o nových leadoch: podpis a overenie

Cron `backend/bin/odosli-pdf.php` pošle na `NACENIME_LEAD_WEBHOOK` (len `https://`) JSON o každom novom leade
(žiadosť o telefonát, PDF so súhlasom s kontaktovaním). Ak je nastavené `NACENIME_LEAD_WEBHOOK_SECRET`,
požiadavka je **podpísaná** (HMAC-SHA256), aby prijímač (napr. rozia.sk) vedel, že ju poslal nacenime.sk a že ju nikto
cestou nezmenil. Bez tajomstva sa webhook správa ako doteraz (bez podpisu).

## Zapnutie

1. Vygeneruj tajomstvo: `openssl rand -hex 32` (min. 32 znakov; kratšie `bin/skontroluj-konfig.php` odmietne).
2. Rovnakú hodnotu ulož na oboch stranách do prostredia servera, **nikdy do repa ani chatu**:
   nacenime: `shared/.env` → `NACENIME_LEAD_WEBHOOK_SECRET=…`; prijímač: napr. `NACENIME_WEBHOOK_SECRET` (názov si určuje prijímač).
3. Najprv nasaď a nastav prijímač, až potom tajomstvo u nacenime. Kým tajomstvo nie je nastavené, podpis sa neposiela.
4. Spusti `php backend/bin/skontroluj-konfig.php`: pri správnom nastavení vypíše „webhook je podpisovaný“.

Výmena tajomstva: prijímač dočasne akceptuje staré aj nové tajomstvo, potom sa zmení nacenime a staré sa odstráni.

## Požiadavka

`POST <NACENIME_LEAD_WEBHOOK>`, `Content-Type: application/json`, telo je JSON (UTF-8, bez escapovania diakritiky).

| Hlavička | Význam |
|---|---|
| `X-Nacenime-Event-Id` | Stabilné ID udalosti, napr. `nacenime-lead-7`. Pri opakovaní doručenia ostáva **rovnaké**. |
| `X-Nacenime-Signature` | `t=<unix sekundy>,v1=<hex>`; len ak je nastavené tajomstvo. |

Podpis: `hex(HMAC_SHA256(tajomstvo, "<t>." + <surové telo požiadavky>))`. Časová pečiatka `t` je pri každom pokuse nová.

Pokus je úspešný len pri odpovedi 2xx. Inak ho cron zopakuje (najviac 5×); preto musí byť prijímač **idempotentný**.

## Čo musí prijímač urobiť

1. Prečítať **surové** telo (nie znovu zserializovaný JSON).
2. Overiť podpis v konštantnom čase a skontrolovať, že `|teraz − t| ≤ 300 s`.
3. Zamietnuť (401) chybný, chýbajúci alebo starý podpis; nespracovať telo.
4. Uložiť `X-Nacenime-Event-Id` s jedinečným obmedzením (napr. `unique (source, event_id)` v `inbound_events`).
   Ak ID už existuje, odpovedať 2xx a nič nespracovať znova (jedna udalosť = jeden záznam).
5. Odpovedať 2xx až po bezpečnom uložení, inak sa lead stratí.

### Príklad (PHP, referenčná implementácia `Nacenime\WebhookPodpis::over`)

```php
$telo = file_get_contents('php://input');
$dovod = \Nacenime\WebhookPodpis::over($_SERVER['HTTP_X_NACENIME_SIGNATURE'] ?? '', $telo, $tajomstvo);
if ($dovod !== null) { http_response_code(401); exit; }
```

### Príklad (TypeScript / Node, napr. route handler v rozia.sk)

```ts
import { createHmac, timingSafeEqual } from "node:crypto";

export function overPodpis(hlavicka: string, telo: string, tajomstvo: string, teraz = Math.floor(Date.now() / 1000)): string | null {
  const m = /^t=(\d{1,12}),v1=([0-9a-f]{64})$/.exec(hlavicka);
  if (!m) return "neplatný formát podpisu";
  const t = Number(m[1]);
  if (Math.abs(teraz - t) > 300) return "časová pečiatka mimo tolerancie";
  const ocakavany = createHmac("sha256", tajomstvo).update(`${t}.${telo}`).digest();
  return timingSafeEqual(ocakavany, Buffer.from(m[2], "hex")) ? null : "podpis nesedí";
}
// v handleri: const telo = await req.text(); (nie req.json(), podpis sa počíta zo surového tela)
```

## Telo (JSON)

Polia vytvára `Oznamovac::data()`: `zdroj`, `id`, `vytvorene`, `ziadost` (`telefonat` | `pdf_suhlas`), `meno`, `telefon`, `email`,
`typ`, `vymera`, `stav`, `adresa`, `psc`, `obec`, `cena_od`, `cena_do`, `vysledok`, `dovod`, `predstava_ceny`, `dom_popis`.
Telo obsahuje osobné údaje, preto je webhook povolený len cez `https://`.

## Testy

`php backend/tests/webhook.php`: jednotkové testy podpisu, diagnostika konfigurácie a doručenie na skutočný HTTP prijímač
vrátane opakovania (rovnaké ID udalosti pri druhom pokuse).
