<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

/**
 * The account a person creates when accepting an invitation. The email is the invitation's.
 */
final readonly class NewAccountData
{
    /**
     * @param  string  $password  In plain text: the User model hashes it.
     */
    public function __construct(
        public string $name,
        public string $password,
    ) {}
}
