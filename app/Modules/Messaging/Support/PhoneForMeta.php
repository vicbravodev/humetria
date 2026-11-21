<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Support\PhoneNumber;

/**
 * The phone as Meta writes it. We store `+52…` (E.164); Meta takes the number without the plus.
 *
 * Supuesto por confirmar (Victor, Fase 2 (a)): Mexico is `52` + 10 digits to send, and the wa_id of an incoming message may come
 * as `521` + 10 digits. Meta's own documentation was not reachable when this was written (vendor summaries disagree, some
 * saying `521`); confirm with a real send and a real STOP. If it is wrong, only this class changes.
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
