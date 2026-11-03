<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Compensación (collective contract calculator). Its panel routes live in routes/compensation.php, required from the
 * company group in routes/company.php. The contracts and listeners of the module are bound here as they appear.
 */
final class CompensationServiceProvider extends ServiceProvider {}
