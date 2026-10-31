<?php

declare(strict_types=1);

namespace App\Modules\Telework\Enums;

/** What the Comisión asks of a workplace: photos from the person, or a remote check it does itself. */
enum EvidenceKind: string
{
    case Photos = 'photos';
    case RemoteCheck = 'remote_check';
}
