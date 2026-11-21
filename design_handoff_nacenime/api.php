<?php
// Vstupný bod API pre /api/* pri nasadení z Gitu. Do nasadzovaného stromu sa kopíruje do design_handoff_nacenime/.
// Aplikácia sa spúšťa zo svojho miesta (backend/public/index.php), takže cesty k .env a databáze (shared) fungujú.
declare(strict_types=1);

require __DIR__ . '/../backend/public/index.php';
