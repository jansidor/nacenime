<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Podpis odchádzajúceho webhooku (HMAC-SHA256). Prijímač (napr. rozia.sk) vďaka nemu vie, že telo poslal nacenime.sk
 * a že ho nikto cestou nezmenil, a vďaka identifikátoru udalosti vie opakované doručenie spracovať iba raz.
 *
 * Podpisuje sa reťazec "<časová pečiatka>.<telo>" tajomstvom z NACENIME_LEAD_WEBHOOK_SECRET.
 * Hlavičky: X-Nacenime-Event-Id (stabilný pre jeden lead, aj pri opakovaní), X-Nacenime-Signature: t=<unix>,v1=<hex>.
 */
final class WebhookPodpis
{
    public const MIN_DLZKA_TAJOMSTVA = 32;
    public const PREDVOLENA_TOLERANCIA = 300; // sekundy

    /** Stabilný identifikátor udalosti: jeden lead = jedna udalosť, nezávisle od počtu pokusov o doručenie. */
    public static function idUdalosti(int $leadId): string
    {
        return 'nacenime-lead-' . $leadId;
    }

    public static function podpis(string $telo, string $tajomstvo, int $cas): string
    {
        return hash_hmac('sha256', $cas . '.' . $telo, $tajomstvo);
    }

    /**
     * Hlavičky pre odoslanie. Bez tajomstva sa neposiela žiaden podpis (správanie ako doteraz), ale event id áno.
     *
     * @return list<string>
     */
    public static function hlavicky(string $telo, string $tajomstvo, string $idUdalosti, int $cas): array
    {
        $h = ['Content-Type: application/json', 'X-Nacenime-Event-Id: ' . $idUdalosti];
        if ($tajomstvo !== '') {
            $h[] = 'X-Nacenime-Signature: t=' . $cas . ',v1=' . self::podpis($telo, $tajomstvo, $cas);
        }
        return $h;
    }

    /**
     * Referenčné overenie na strane prijímača: platný formát, čerstvá časová pečiatka, zhodný podpis
     * (porovnanie v konštantnom čase). Vracia dôvod zamietnutia alebo null, ak je všetko v poriadku.
     */
    public static function over(string $hlavickaPodpisu, string $telo, string $tajomstvo, ?int $teraz = null, int $tolerancia = self::PREDVOLENA_TOLERANCIA): ?string
    {
        if ($tajomstvo === '') {
            return 'tajomstvo nie je nastavené';
        }
        if (!preg_match('/^t=(\d{1,12}),v1=([0-9a-f]{64})$/', $hlavickaPodpisu, $m)) {
            return 'neplatný formát podpisu';
        }
        $cas = (int) $m[1];
        if (abs(($teraz ?? time()) - $cas) > $tolerancia) {
            return 'časová pečiatka mimo tolerancie';
        }
        return hash_equals(self::podpis($telo, $tajomstvo, $cas), $m[2]) ? null : 'podpis nesedí';
    }
}
