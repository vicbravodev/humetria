<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Notifications\CompanyReactivated;
use App\Modules\Tenancy\Notifications\CompanyRestricted;
use App\Modules\Tenancy\Notifications\ToolsChanged;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * What the owner of a company is told when the platform changes what it can do: it stopped, it came back, its tools changed.
 * The change is already made; when there is nobody to tell (no owner, or one who never verified the email) it says so in the
 * log and goes on.
 */
final readonly class OwnerNotices
{
    public function restricted(Tenant $tenant, TenantRestriction $restriction, string $reason): void
    {
        $this->send($tenant, $restriction->event(), fn (): CompanyRestricted => new CompanyRestricted(CompanyDirectory::summaryOf($tenant), $restriction, $reason, $this->support()));
    }

    public function reactivated(Tenant $tenant): void
    {
        $this->send($tenant, 'tenant.reactivated', fn (): CompanyReactivated => new CompanyReactivated(CompanyDirectory::summaryOf($tenant), $this->support()));
    }

    /**
     * @param  list<Module>  $added
     * @param  list<Module>  $removed
     */
    public function toolsChanged(Tenant $tenant, array $added, array $removed, ?CarbonImmutable $removedOn): void
    {
        if ($added === [] && $removed === []) {
            return;
        }

        $this->send($tenant, 'tenant.modules_changed', fn (): ToolsChanged => new ToolsChanged(
            CompanyDirectory::summaryOf($tenant),
            self::labels($added),
            self::labels($removed),
            $removedOn === null ? null : DisplayDate::day($removedOn),
            $removedOn !== null && $removedOn->setTimezone(config()->string('app.display_timezone'))->toDateString() <= now()->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            $this->support(),
        ));
    }

    /**
     * @param  list<Module>  $modules
     * @return list<string>
     */
    private static function labels(array $modules): array
    {
        $labels = [];

        foreach ($modules as $module) {
            $labels[] = $module->label();
        }

        return $labels;
    }

    /**
     * @param  callable(): Notification  $notice
     */
    private function send(Tenant $tenant, string $event, callable $notice): void
    {
        $owner = $tenant->ownerMembership()->with('user')->first()?->user;

        if ($owner === null || ! $owner->hasVerifiedEmail()) {
            // A warning: somebody should know the owner heard nothing, and it is not the line a change writes.
            Log::warning('tenant.owner_not_notified', ['tenant_id' => $tenant->id, 'event' => $event, 'why' => $owner === null ? 'no_owner' : 'unverified_email']);

            return;
        }

        $owner->notify($notice());
    }

    private function support(): ?string
    {
        $email = config('app.support_email');

        return is_string($email) && $email !== '' ? $email : null;
    }
}
