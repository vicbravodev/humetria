<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company data: every query of the model sees only the current company's rows, and with no company resolved it fails
 * instead of reading every company's. The model's table has a tenant_id column.
 */
trait BelongsToCurrentTenant
{
    public static function bootBelongsToCurrentTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $query->where($query->qualifyColumn('tenant_id'), resolve(CurrentTenant::class)->id());
        });
    }
}
