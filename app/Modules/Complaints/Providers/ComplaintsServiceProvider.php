<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Providers;

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Support\CompanyComplaintChannels;
use Illuminate\Support\ServiceProvider;

/**
 * NOM-035, complaints channel (8.1 b). Its panel routes live in routes/complaints.php, required from the company group in
 * routes/company.php; the public ones (/c/{code}) are routes/complaints-public.php. Contracts, listeners and limiters are
 * bound here as they appear.
 */
final class ComplaintsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ComplaintChannels::class, CompanyComplaintChannels::class);
    }
}
