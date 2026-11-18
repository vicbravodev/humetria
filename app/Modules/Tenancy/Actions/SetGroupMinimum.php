<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\CompanyGroupMinimums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The owner sets the company's minimum of people per group (criterio C-23), or with null goes back to the platform's. What
 * already launched keeps the minimum it froze; only what launches from now on uses the new one.
 */
final readonly class SetGroupMinimum
{
    public function __construct(private RecordAudit $recordAudit, private CompanyGroupMinimums $minimums) {}

    public function handle(Tenant $tenant, ?int $minimum): void
    {
        if ($minimum !== null && ($minimum < GroupMinimums::LOWEST || $minimum > GroupMinimums::HIGHEST)) {
            throw new InvalidArgumentException(sprintf('A group minimum goes from %d to %d, not %d.', GroupMinimums::LOWEST, GroupMinimums::HIGHEST, $minimum));
        }

        $from = $tenant->min_group;

        if ($from === $minimum) {
            return;
        }

        DB::transaction(function () use ($tenant, $from, $minimum): void {
            $tenant->min_group = $minimum;
            $tenant->save();

            $change = ['from' => $from, 'to' => $minimum];
            Log::info('tenant.group_minimum_changed', ['tenant_id' => $tenant->id, ...$change]);
            $this->recordAudit->handle('tenant.group_minimum_changed', $tenant, $change);
        });

        $this->minimums->forget();
    }
}
