<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Support\PhoneNumber;

/**
 * The phone as Meta writes it. We store `+52…` (E.164); Meta takes the number without the plus.
 *
 * Verified against Meta's sandbox on 2026-11-18: Meta accepts `52` + 10 digits to send and answers, in the response and in every
 * webhook (`wa_id`, `recipient_id`, `from`), with `521` + 10 digits. fromWaId() drops that 1, so the hash of a STOP equals the
 * hash of the stored phone (docs/operations/whatsapp-validacion.md).
 */
final class PhoneForMeta
{
    /** `52` + 10 digits, or null for anything else: v1 only writes to Mexican mobiles. */
    public function toMeta(?string $e164): ?string
    {
        $normalized = PhoneNumber::e164($e164);

        return $normalized !== null && preg_match('/\A\+52(\d{10})\z/', $normalized, $match) === 1 ? '52'.$match[1] : null;
    }

    /** `+52` + 10 digits for a Mexican wa_id (the 1 of `521` is dropped), the E.164 as it is for another country, null if none. */
    public function fromWaId(string $waId): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $waId);

        // PhoneNumber::e164() already drops the 1 of +521.
        return $digits === '' ? null : PhoneNumber::e164('+'.$digits);
    }
}
