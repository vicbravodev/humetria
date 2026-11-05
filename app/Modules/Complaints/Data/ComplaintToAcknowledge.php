<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Data;

/**
 * A complaint waiting for its acknowledgment, without its content: «Una denuncia espera acuse de recibo» and the last day to
 * acknowledge it, as Y-m-d.
 */
final readonly class ComplaintToAcknowledge
{
    public function __construct(
        public string $id,
        public string $title,
        public string $dueOn,
    ) {}
}
