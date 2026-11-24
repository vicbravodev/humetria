<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Data\FailureCause;

/**
 * Meta's error codes (https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes, checked 2026-11-18) as causes.
 */
final class WhatsAppErrorMap
{
    /** The gateway's view: the HTTP status is the fallback when the code says nothing, and a 429 is always a rate limit. */
    public function fromResponse(int $httpStatus, ?int $metaCode): FailureCause
    {
        if ($httpStatus === 429) {
            return FailureCause::RateLimited;
        }

        $cause = $metaCode === null ? FailureCause::UnknownError : $this->fromCode($metaCode);

        if ($cause === FailureCause::UnknownError && $httpStatus >= 500) {
            return FailureCause::ServerError;
        }

        return $cause;
    }

    /** The webhook's view: only the code. */
    public function fromCode(int $metaCode): FailureCause
    {
        return match ($metaCode) {
            190 => FailureCause::InvalidToken,
            132000, 132001, 132015, 132016 => FailureCause::TemplateNotApproved,
            131026 => FailureCause::NoWhatsappAccount,
            131047 => FailureCause::OutsideWindow,
            130429, 131056, 80007 => FailureCause::RateLimited,
            131048 => FailureCause::SpamLimited,
            131049 => FailureCause::EcosystemBlocked,
            131000, 131016 => FailureCause::ServerError,
            default => FailureCause::UnknownError,
        };
    }
}
