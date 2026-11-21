<?php
// Vstupný bod API pre crm.nacenime.sk (priečinok crm/ v nasadzovanom strome). Len cez tento súbor sú dostupné /api/crm/*.
declare(strict_types=1);

define('NACENIME_CRM_VSTUP', true);
require __DIR__ . '/../backend/public/index.php';
