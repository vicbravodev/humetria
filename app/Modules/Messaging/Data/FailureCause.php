<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * Why a message did not go out or did not arrive (rule 8 and 9 of the design).
 */
enum FailureCause: string
{
    case InvalidToken = 'invalid_token';
    case TemplateNotApproved = 'template_not_approved';
    case NoWhatsappAccount = 'no_whatsapp_account';
    case OutsideWindow = 'outside_window';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case TimedOut = 'timed_out';
    case RetriesExhausted = 'retries_exhausted';
    case SpamLimited = 'spam_limited';
    case EcosystemBlocked = 'ecosystem_blocked';
    case OptedOut = 'opted_out';
    case InvitationClosed = 'invitation_closed';
    case Paused = 'paused';
    case Disabled = 'disabled';
    case Unresolved = 'unresolved';
    case UnknownError = 'unknown_error';

    /** Worth another attempt with the job's backoff. */
    public function retryable(): bool
    {
        return in_array($this, [self::RateLimited, self::ServerError], true);
    }

    /** The definitive failures after which the person gets the mail instead (rule 9). */
    public function fallsBackToMail(): bool
    {
        return in_array($this, [self::NoWhatsappAccount, self::TemplateNotApproved, self::InvalidToken, self::RetriesExhausted, self::OptedOut], true);
    }
}
