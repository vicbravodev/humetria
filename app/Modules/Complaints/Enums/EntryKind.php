<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Enums;

/** What an entry of a case is: a message in the thread, an internal note, or the case's own history. */
enum EntryKind: string
{
    case ReporterMessage = 'reporter_message';
    case HandlerMessage = 'handler_message';
    case InternalNote = 'internal_note';
    case StatusChange = 'status_change';
    case AssignmentChange = 'assignment_change';
    case Recusal = 'recusal';

    /** Whether the person who presented the complaint reads it: their own messages and the handlers' answers, nothing else. */
    public function reporterReads(): bool
    {
        return $this === self::ReporterMessage || $this === self::HandlerMessage;
    }
}
