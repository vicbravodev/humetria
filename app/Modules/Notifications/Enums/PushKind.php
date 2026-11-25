<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * The only thing a push says: which of the two things happened. It travels encrypted to the phone, and the app (not the server)
 * decides the text shown for each (`noticeOf` in `resources/js/lib/pwa/sw-rules.ts`). A new kind needs its text there, its line in
 * the privacy notice and its test. The values are the words the app reads; never a free text, a name, a time or a decision.
 */
enum PushKind: string
{
    /** RH reviewed a mark made without connection (verified, or incorrect with its correction). */
    case MarkReviewed = 'mark_reviewed';

    /** RH answered a request to correct, approving or rejecting it. */
    case CorrectionResolved = 'correction_resolved';
}
