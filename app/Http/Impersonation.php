<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Contracts\Session\Session;

/**
 * Who, if anyone, is impersonating the logged-in user. The superadmin's id
 * lives in the session; it is the only trace that tells a superadmin's
 * actions apart from the owner's they are acting as.
 */
final readonly class Impersonation
{
    public const string SESSION_KEY = 'impersonator_id';

    public function __construct(private Session $session) {}

    public function impersonatorId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }

    public function isActive(): bool
    {
        return $this->impersonatorId() !== null;
    }
}
