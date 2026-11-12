<?php
// Vstupný bod API pre zdieľaný hosting. Jediný PHP súbor vo verejnom priečinku.
// Aplikácia (src, vendor, cenník, .env, databáza) leží v priečinku app/ MIMO verejného priečinka.
declare(strict_types=1);

require __DIR__ . '/../app/public/index.php';
