<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

/**
 * The addresses of the person's pages, for any module's emails: on the app's address, of the current company, and with no
 * credential in them. A person without a session who opens one enters first.
 */
interface WorkerLinks
{
    /** The person's entrance: `/me/{slug}`. */
    public function home(): string;

    /**
     * The page of the named route of the person's pages (`worker.*`, `portal.*`), for the current company.
     *
     * @param  array<string, mixed>  $parameters  the route's other parameters
     */
    public function to(string $routeName, array $parameters = []): string;
}
