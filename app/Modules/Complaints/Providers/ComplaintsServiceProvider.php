<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Providers;

use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Listeners\EndHandlerOnMemberRemoval;
use App\Modules\Complaints\Support\AddressKey;
use App\Modules\Complaints\Support\CompanyComplaintChannels;
use App\Modules\Complaints\Support\RandomReferenceSource;
use App\Modules\Complaints\Support\ReferenceSource;
use App\Modules\Tenancy\Events\MemberRemoved;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->bind(ReferenceSource::class, RandomReferenceSource::class);
    }

    public function boot(): void
    {
        Event::listen(MemberRemoved::class, EndHandlerOnMemberRemoval::class);

        // Keyed by a hash of the address, never the address; a whole office behind one address must still be able to use it.
        RateLimiter::for('complaints-view', fn (Request $request): Limit => Limit::perHour(config()->integer('complaints.rate_limits.views_per_hour'))->by(AddressKey::for($request)));
        RateLimiter::for('complaints-submit', fn (Request $request): Limit => Limit::perHour(config()->integer('complaints.rate_limits.submissions_per_hour'))->by(AddressKey::for($request)));
    }
}
