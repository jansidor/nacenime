<?php
declare(strict_types=1);

namespace Nacenime;

/** Odošle čakajúce PDF e-maily. Idempotentné: odoslané sa neposielajú znova, zlyhania sa opakujú najviac N-krát. */
final class OdosielacPdf
{
    /** @param callable(string, string, string, string, string): void $posli (komu, predmet, telo, pdf, nazov) */
    public function __construct(private Storage $storage, private Pdf $pdf, private $posli, private int $maxPokusov = 5) {}

    /** @return array{odoslane: int, zlyhalo: int, preskocene: int} */
    public function spusti(string $makler = ''): array
    {
        $r = ['odoslane' => 0, 'zlyhalo' => 0, 'preskocene' => 0];
        foreach ($this->storage->cakajucePdf($this->maxPokusov) as $k) {
            $odhad = $k['odhad_id'] !== null ? $this->storage->odhad((int) $k['odhad_id']) : null;
            if ($odhad === null) {
                $this->storage->oznacOdoslane((int) $k['id'], 2);
                $r['preskocene']++;
                continue;
            }
            try {
                ($this->posli)($k['email'], 'Váš odhad ceny nehnuteľnosti', "Dobrý deň,\n\nv prílohe nájdete orientačný odhad ceny. Nejde o znalecký posudok.\n\nnacenime.sk", $this->pdf->vytvor($odhad, $makler), 'odhad-nacenime.pdf');
                $this->storage->oznacOdoslane((int) $k['id']);
                $r['odoslane']++;
            } catch (\Throwable $e) {
                error_log('PDF e-mail #' . $k['id'] . ': ' . $e->getMessage());
                $this->storage->pokusZlyhal((int) $k['id']);
                $r['zlyhalo']++;
            }
        }
        return $r;
    }
}
