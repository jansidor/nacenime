<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Proxy na Google Places API (New). Kľúč je len na serveri. Pri akejkoľvek chybe Google
 * vracia príznak `zaloha` a frontend prepne na dve polia (ulica + obec), formulár sa nezablokuje.
 */
final class Adresy
{
    private const AUTOCOMPLETE = 'https://places.googleapis.com/v1/places:autocomplete';
    private const DETAIL = 'https://places.googleapis.com/v1/places/';
    // Obdĺžnik okolo Liptova, Turca a Oravy; presný filter robí kontrola obce v Place Details.
    private const REGION = ['low' => ['latitude' => 48.85, 'longitude' => 18.30], 'high' => ['latitude' => 49.45, 'longitude' => 20.30]];

    /** @var callable(string, string, array<string, string>, ?string): array{0: int, 1: string} */
    private $http;

    /** @param (callable(string, string, array<string, string>, ?string): array{0: int, 1: string})|null $http */
    public function __construct(private Odhad $odhad, private Config $config, ?callable $http = null, private ?Storage $storage = null)
    {
        $this->http = $http ?? self::curl(...);
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    public function navrhy(string $q, string $token): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [200, ['navrhy' => []]]; // hľadá sa až od 3 znakov
        }
        if (mb_strlen($q) > 200) {
            return [422, ['chyby' => ['q' => 'Adresa je príliš dlhá.']]];
        }
        $telo = ['input' => $q, 'languageCode' => 'sk', 'regionCode' => 'sk', 'includedRegionCodes' => ['sk'],
            'includedPrimaryTypes' => ['route', 'street_address', 'premise', 'subpremise'],
            'locationRestriction' => ['rectangle' => self::REGION]];
        if (self::tokenOk($token)) {
            $telo['sessionToken'] = $token;
        }
        $r = $this->google('POST', self::AUTOCOMPLETE, $telo, 'suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat');
        if ($r === null) {
            return [200, ['zaloha' => true, 'navrhy' => []]];
        }
        $out = [];
        $navrhy = is_array($r['suggestions'] ?? null) ? $r['suggestions'] : [];
        foreach (array_slice($navrhy, 0, 5) as $s) {
            $p = is_array($s) ? ($s['placePrediction'] ?? null) : null;
            if (!is_array($p) || !is_string($p['placeId'] ?? null)) {
                continue;
            }
            $out[] = ['id' => $p['placeId'],
                'hlavny' => self::text($p['structuredFormat']['mainText']['text'] ?? null),
                'vedlajsi' => self::text($p['structuredFormat']['secondaryText']['text'] ?? null)];
        }
        return [200, ['navrhy' => $out]];
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    public function detail(string $placeId, string $token): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{10,300}$/', $placeId) !== 1) {
            return [422, ['chyby' => ['id' => 'Neplatná adresa.']]];
        }
        $url = self::DETAIL . $placeId . '?languageCode=sk' . (self::tokenOk($token) ? '&sessionToken=' . $token : '');
        $r = $this->google('GET', $url, null, 'formattedAddress,addressComponents,location'); // Essentials
        $lat = is_array($r['location'] ?? null) ? ($r['location']['latitude'] ?? null) : null;
        $lng = is_array($r['location'] ?? null) ? ($r['location']['longitude'] ?? null) : null;
        if ($r === null || !is_numeric($lat) || !is_numeric($lng)) {
            return [200, ['zaloha' => true]];
        }
        $c = [];
        foreach (is_array($r['addressComponents'] ?? null) ? $r['addressComponents'] : [] as $a) {
            if (!is_array($a) || !is_array($a['types'] ?? null)) {
                continue;
            }
            foreach ($a['types'] as $t) {
                if (is_string($t)) {
                    $c[$t] ??= self::text($a['longText'] ?? null);
                }
            }
        }
        $obec = $c['locality'] ?? $c['sublocality'] ?? $c['administrative_area_level_3'] ?? '';
        $ulica = trim(($c['route'] ?? '') . ' ' . ($c['street_number'] ?? ''));
        return [200, [
            'ulica' => $ulica, 'psc' => $c['postal_code'] ?? '', 'obec' => $obec,
            'adresa' => self::text($r['formattedAddress'] ?? null),
            'lat' => (float) $lat, 'lng' => (float) $lng,
            'v_regione' => $this->odhad->najdiObec($obec) !== null,
        ]];
    }

    /** Záloha: kontrola obce zo zoznamu (bez ohľadu na diakritiku a veľkosť písmen). @return array{0: int, 1: array<string, mixed>} */
    public function obec(string $obec): array
    {
        $o = trim($obec);
        if ($o === '' || mb_strlen($o) > 100) {
            return [422, ['chyby' => ['obec' => 'Zadajte obec.']]];
        }
        $n = $this->odhad->najdiObec($o);
        return [200, ['v_regione' => $n !== null, 'obec' => $n['obec'] ?? $o, 'okres' => $n['okres'] ?? null]];
    }

    private static function text(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }

    private static function tokenOk(string $t): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{8,64}$/', $t) === 1;
    }

    /** @return array<string, mixed>|null null = Google nefunguje alebo kľúč chýba */
    private function google(string $metoda, string $url, ?array $telo, string $fieldMask): ?array
    {
        $kluc = $this->config->get('GOOGLE_API_KEY');
        if ($kluc === '') {
            return null;
        }
        // Vlastný denný strop volaní na Google (Google limity nejdú znížiť). Po vyčerpaní sa použije záloha bez Google.
        $strop = (int) $this->config->get('GOOGLE_DENNY_LIMIT', '3000');
        if ($this->storage !== null && $strop > 0) {
            $dnes = $this->storage->zvysDenne('google');
            if ($dnes > $strop) {
                if ($dnes === $strop + 1) {
                    error_log("Google Places: denný limit $strop volaní vyčerpaný, do polnoci (UTC) beží záloha bez Google.");
                }
                return null;
            }
        }
        try {
            [$kod, $odpoved] = ($this->http)($metoda, $url, [
                'X-Goog-Api-Key' => $kluc, 'X-Goog-FieldMask' => $fieldMask, 'Content-Type' => 'application/json',
            ], $telo === null ? null : json_encode($telo, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            error_log('Google Places: ' . $e->getMessage()); // bez kľúča a URL
            return null;
        }
        $j = $kod === 200 ? json_decode($odpoved, true) : null;
        if (!is_array($j)) {
            error_log("Google Places: HTTP $kod");
            return null;
        }
        return $j;
    }

    /** @return array{0: int, 1: string} */
    private static function curl(string $metoda, string $url, array $hlavicky, ?string $telo): array
    {
        $ch = curl_init($url);
        $h = [];
        foreach ($hlavicky as $k => $v) {
            $h[] = "$k: $v";
        }
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metoda, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 3] + ($telo !== null ? [CURLOPT_POSTFIELDS => $telo] : []));
        $odpoved = curl_exec($ch);
        $kod = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($odpoved === false) {
            throw new \RuntimeException($err);
        }
        return [$kod, (string) $odpoved];
    }
}
