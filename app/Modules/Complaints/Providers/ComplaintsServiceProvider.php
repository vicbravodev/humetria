<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Providers;

use App\Modules\Complaints\Contracts\ComplaintActivity;
use App\Modules\Complaints\Contracts\ComplaintChannels;
use App\Modules\Complaints\Contracts\ComplaintsToAcknowledge;
use App\Modules\Complaints\Listeners\EndAssignmentsOnRoleConflict;
use App\Modules\Complaints\Listeners\EndHandlerOnMemberRemoval;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\AddressKey;
use App\Modules\Complaints\Support\CompanyComplaintActivity;
use App\Modules\Complaints\Support\CompanyComplaintChannels;
use App\Modules\Complaints\Support\CompanyComplaintsToAcknowledge;
use App\Modules\Complaints\Support\ComplaintsWorkerSection;
use App\Modules\Complaints\Support\RandomReferenceSource;
use App\Modules\Complaints\Support\ReferenceSource;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Events\MemberRemoved;
use App\Modules\Tenancy\Events\MemberRoleChanged;
use App\Modules\Tenancy\Events\RolePermissionsChanged;
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
        $this->app->bind(ComplaintActivity::class, CompanyComplaintActivity::class);
        $this->app->bind(ComplaintsToAcknowledge::class, CompanyComplaintsToAcknowledge::class);
        $this->app->bind(ReferenceSource::class, RandomReferenceSource::class);
        $this->app->tag([ComplaintsWorkerSection::class], 'worker.sections');
    }

    public function boot(): void
    {
        Event::listen(MemberRemoved::class, EndHandlerOnMemberRemoval::class);
        Event::listen(MemberRoleChanged::class, EndAssignmentsOnRoleConflict::class);
        Event::listen(MemberPermissionsChanged::class, EndAssignmentsOnRoleConflict::class);
        Event::listen(RolePermissionsChanged::class, [EndAssignmentsOnRoleConflict::class, 'handleRole']);

        // Keyed by a hash of the address, never the address; a whole office behind one address must still be able to use it.
        RateLimiter::for('complaints-view', fn (Request $request): Limit => Limit::perHour(config()->integer('complaints.rate_limits.views_per_hour'))->by(AddressKey::for($request)));
        RateLimiter::for('complaints-follow-up', fn (Request $request): Limit => Limit::perHour(config()->integer('complaints.rate_limits.follow_ups_per_hour'))->by(AddressKey::for($request)));
        // The daily limit keeps one address to a small share of the channel's daily cap (audit C6); the demo channel gets a room's worth of both.
        RateLimiter::for('complaints-submit', function (Request $request): array {
            $prefix = $this->isDemoChannel($request) ? 'complaints.rate_limits.demo_' : 'complaints.rate_limits.';

            return [
                Limit::perHour(config()->integer($prefix.'submissions_per_hour'))->by(AddressKey::for($request)),
                Limit::perDay(config()->integer($prefix.'submissions_per_day'))->by('day:'.AddressKey::for($request)),
            ];
        });
    }

    /** Whether the code of the request is the demo company's channel, where a whole room files from one address. */
    private function isDemoChannel(Request $request): bool
    {
        $demo = $this->app->make(DemoCompanies::class)->id();
        $code = $request->route('code');

        return $demo !== null && is_string($code)
            && ComplaintChannel::query()->withoutGlobalScopes()->where('tenant_id', $demo)->where('access_code', strtoupper(trim($code)))->exists();
    }
}
