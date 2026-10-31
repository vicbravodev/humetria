<?php

declare(strict_types=1);

namespace App\Modules\Telework\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * NOM-037 (teletrabajo). Its panel routes live in routes/telework.php, required from the company group in
 * routes/company.php; the contracts and listeners of the module are bound here as they appear.
 */
final class TeleworkServiceProvider extends ServiceProvider {}
