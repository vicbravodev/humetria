<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;

/**
 * The person's pages on the app's address (never the request's host), for the current company.
 */
final readonly class RouteWorkerLinks implements WorkerLinks
{
    public function __construct(private Companies $companies) {}

    public function home(): string
    {
        return $this->to('worker.home');
    }

    public function to(string $routeName, array $parameters = []): string
    {
        return rtrim(config()->string('app.url'), '/').route($routeName, ['tenant' => $this->companies->current()->slug, ...$parameters], absolute: false);
    }
}
