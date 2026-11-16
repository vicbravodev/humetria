<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * How inviting a person went: `sent` by email (RH never sees the link), `without_email` (the link, shown to RH once, to
 * deliver by hand) or `unavailable` (the person left or is not the company's; nothing was issued).
 */
final readonly class InvitationOutcome
{
    public const string SENT = 'sent';

    public const string WITHOUT_EMAIL = 'without_email';

    public const string UNAVAILABLE = 'unavailable';

    /** @param  self::SENT|self::WITHOUT_EMAIL|self::UNAVAILABLE  $status */
    public function __construct(
        public string $status,
        public ?string $url = null,
    ) {}
}
