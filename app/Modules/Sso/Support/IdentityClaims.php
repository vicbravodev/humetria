<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

/** What a verified ID token says about the person: their email, and whether the provider vouches for it. */
final readonly class IdentityClaims
{
    public function __construct(public ?string $email, public bool $emailVerified) {}

    /**
     * Entra has no email_verified: its own proof that the tenant owns the domain (xms_edov) stands in for it.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function from(array $claims): self
    {
        $email = $claims['email'] ?? null;

        return new self(
            is_string($email) ? $email : null,
            self::truthy($claims['email_verified'] ?? null) || self::truthy($claims['xms_edov'] ?? null),
        );
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
