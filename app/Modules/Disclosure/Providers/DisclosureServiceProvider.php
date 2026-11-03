<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * NOM-035 policy of prevention, its disclosure and the permanent page of the workers (num. 5.1, 5.7 and 7.8). Its panel routes
 * live in routes/disclosure.php, required from the company group in routes/company.php; the public ones are
 * routes/workers.php. The contracts, listeners and limits of the module are bound here as they appear.
 */
final class DisclosureServiceProvider extends ServiceProvider {}
