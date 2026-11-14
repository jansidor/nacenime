<?php
declare(strict_types=1);

namespace Nacenime;

/** Konfigurácia z premenných prostredia a voliteľného súboru .env (jednoduchý KEY=value). */
final class Config
{
    /** @param array<string, string> $v */
    public function __construct(private array $v) {}

    public static function nacitaj(string $envSubor, array $prostredie = []): self
    {
        $v = [];
        if (is_file($envSubor)) {
            foreach (file($envSubor, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $riadok) {
                $riadok = trim($riadok);
                if ($riadok === '' || $riadok[0] === '#' || !str_contains($riadok, '=')) {
                    continue;
                }
                [$k, $hodnota] = explode('=', $riadok, 2);
                $v[trim($k)] = trim($hodnota, " \t\"'");
            }
        }
        // Skutočné premenné prostredia majú prednosť pred .env.
        foreach ($prostredie ?: (getenv() ?: []) as $k => $hodnota) {
            if (is_string($hodnota) && str_starts_with($k, 'NACENIME_')) {
                $v[$k] = $hodnota;
            }
        }
        return new self($v);
    }

    public function get(string $kluc, string $predvolene = ''): string
    {
        return $this->v['NACENIME_' . $kluc] ?? $this->v[$kluc] ?? $predvolene;
    }

    /** @return list<string> */
    public function zoznam(string $kluc): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->get($kluc)))));
    }
}
