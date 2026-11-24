<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

/**
 * Why a delivery of a mark made without a connection was kept as rejected (criterio 3.3.1): the reasons that are stored.
 * The ones that are only answered (an invalid ticket or signature, a reused ticket) leave no row.
 */
enum OfflineRejection: string
{
    case TicketExpired = 'ticket_expired';
    case PersonInactive = 'person_inactive';
    case AgreementNotAccepted = 'agreement_not_accepted';
    case SequenceConflict = 'sequence_conflict';
    case WrongCenter = 'wrong_center';
    case PassExpired = 'pass_expired';
}
