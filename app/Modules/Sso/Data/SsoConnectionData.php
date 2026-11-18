<?php

declare(strict_types=1);

namespace App\Modules\Sso\Data;

/**
 * What the owner typed: a blank secret keeps the stored one.
 */
final readonly class SsoConnectionData
{
    /**
     * @param  list<string>  $domains  lowercase, without repeats.
     */
    public function __construct(
        public string $issuer,
        public string $clientId,
        public ?string $clientSecret,
        public array $domains,
    ) {}
}
