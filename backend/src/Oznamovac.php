<?php
declare(strict_types=1);

namespace Nacenime;

/**
 * Oznámi maklérovi nový lead (žiadosť o telefonát, PDF so súhlasom s kontaktovaním) e-mailom a voliteľne webhookom.
 * Beží z cronu spolu s odosielaním PDF. Idempotentné: oznámený lead sa neoznamuje znova, zlyhania sa opakujú najviac N-krát.
 */
final class Oznamovac
{
    /**
     * @param callable(string, string, string, ?string): void $posliText (komu, predmet, telo, odpovedNa)
     * @param list<string> $prijemcovia
     * @param (callable(array<string, mixed>): void)|null $webhook
     */
    public function __construct(private Storage $storage, private $posliText, private array $prijemcovia, private $webhook = null, private int $maxPokusov = 5, private string $crmUrl = '') {}

    /** @return array{oznamene: int, zlyhalo: int} */
    public function spusti(): array
    {
        $r = ['oznamene' => 0, 'zlyhalo' => 0];
        if ($this->prijemcovia === [] && $this->webhook === null) {
            return $r; // nič nie je nastavené: leady ostávajú neoznámené, kým sa nastavia
        }
        foreach ($this->storage->cakajuceOznamenia($this->maxPokusov) as $l) {
            try {
                [$predmet, $telo] = self::sprava($l, $this->crmUrl);
                foreach ($this->prijemcovia as $komu) {
                    ($this->posliText)($komu, $predmet, $telo, $l['email'] ?: null);
                }
                if ($this->webhook !== null) {
                    ($this->webhook)(self::data($l));
                }
                $this->storage->oznacOznamene((int) $l['id']);
                $r['oznamene']++;
            } catch (\Throwable $e) {
                error_log('Oznámenie leadu #' . $l['id'] . ': ' . $e->getMessage());
                $this->storage->oznamenieZlyhalo((int) $l['id']);
                $r['zlyhalo']++;
            }
        }
        return $r;
    }

    /** @param array<string, mixed> $l @return array{0: string, 1: string} */
    public static function sprava(array $l, string $crmUrl = ''): array
    {
        $telefonat = $l['druh'] === 'makler';
        $nehnutelnost = trim(($l['typ'] ?? 'Nehnuteľnosť') . ($l['vymera'] !== null ? ', ' . self::cislo((float) $l['vymera']) . ' m²' : ''));
        $predmet = 'Nový lead: ' . ($telefonat ? 'zavolať' : 'PDF + súhlas') . ' – ' . $nehnutelnost . ($l['obec'] ? ', ' . $l['obec'] : '');
        $adresa = $l['bez_adresy'] ? 'bez adresy (' . ($l['obec'] ?? '') . ')' : trim(implode(', ', array_filter([$l['adresa'], trim(($l['psc'] ?? '') . ' ' . ($l['obec'] ?? ''))])));
        $odhad = $l['cena_od'] !== null ? self::cislo((float) $l['cena_od']) . ' – ' . self::cislo((float) $l['cena_do']) . ' €' : 'bez rozpätia (' . ($l['vysledok'] ?? '–') . ')';
        $riadky = [
            'Nový lead z nacenime.sk',
            '',
            'Žiadosť: ' . ($telefonat ? 'Cena od makléra – chce, aby ste zavolali' : 'Odhad v PDF so súhlasom s kontaktovaním'),
            !empty($l['meno']) ? 'Meno: ' . $l['meno'] : null,
            $l['telefon'] ? 'Telefón: ' . $l['telefon'] : null,
            $l['email'] ? 'E-mail: ' . $l['email'] : null,
            'Kedy: ' . self::cas((string) $l['vytvorene']),
            '',
            'Nehnuteľnosť: ' . $nehnutelnost . ($l['stav'] ? ', ' . $l['stav'] : ''),
            'Adresa: ' . ($adresa !== '' ? $adresa : '–'),
            'Odhad: ' . $odhad,
            $l['dovod'] ? 'Dôvod: ' . $l['dovod'] : null,
            $l['predstava_ceny'] !== null ? 'Predstava ceny: ' . self::cislo((float) $l['predstava_ceny']) . ' €' : null,
            '',
            $telefonat ? 'Zavolajte čo najskôr, ideálne do hodiny.' : 'Klient dostal PDF a súhlasil s kontaktovaním.',
            $crmUrl !== '' ? 'Otvoriť v CRM: ' . rtrim($crmUrl, '/') : null,
            '',
            'Automatická správa z nacenime.sk',
        ];
        return [$predmet, implode("\n", array_filter($riadky, fn($x) => $x !== null))];
    }

    /** Dáta pre webhook (n8n, CRM). @param array<string, mixed> $l @return array<string, mixed> */
    public static function data(array $l): array
    {
        $num = fn($x) => $x === null ? null : (is_numeric($x) ? $x + 0 : null);
        return [
            'zdroj' => 'nacenime.sk', 'id' => (int) $l['id'], 'vytvorene' => $l['vytvorene'],
            'ziadost' => $l['druh'] === 'makler' ? 'telefonat' : 'pdf_suhlas',
            'meno' => $l['meno'] ?? null, 'telefon' => $l['telefon'], 'email' => $l['email'],
            'typ' => $l['typ'], 'vymera' => $num($l['vymera']), 'stav' => $l['stav'],
            'adresa' => $l['adresa'], 'psc' => $l['psc'], 'obec' => $l['obec'],
            'cena_od' => $num($l['cena_od']), 'cena_do' => $num($l['cena_do']), 'vysledok' => $l['vysledok'],
            'dovod' => $l['dovod'], 'predstava_ceny' => $num($l['predstava_ceny']),
        ];
    }

    /** Webhook: POST JSON, do 5 s, úspech len pri 2xx. */
    public static function webhook(string $url): callable
    {
        return function (array $data) use ($url): void {
            $c = curl_init($url);
            curl_setopt_array($c, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $ok = curl_exec($c);
            $kod = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            $chyba = curl_error($c);
            curl_close($c);
            if ($ok === false || $kod < 200 || $kod >= 300) {
                throw new \RuntimeException("Webhook vrátil $kod $chyba");
            }
        };
    }

    private static function cislo(float $n): string
    {
        return number_format($n, fmod($n, 1.0) === 0.0 ? 0 : 1, ',', "\u{00A0}");
    }

    private static function cas(string $iso): string
    {
        try {
            return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('Europe/Bratislava'))->format('j. n. Y H:i');
        } catch (\Exception) {
            return $iso;
        }
    }
}
