<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Enums\ModuleSource;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reason a company has, or does not have, a module, and until when. Read from inside a company (its menu, its routes) and
 * from the console across companies, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $module
 * @property ModuleSource $source
 * @property string|null $reason
 * @property CarbonImmutable|null $expires_at
 * @property int|null $granted_by
 */
#[Fillable(['tenant_id', 'module', 'source', 'reason', 'expires_at', 'granted_by'])]
#[UseFactory(TenantModuleFactory::class)]
final class TenantModule extends Model
{
    /** @use HasFactory<TenantModuleFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The rows that count now: they have no end, or it has not come.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $open) => $open->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['source' => ModuleSource::class, 'expires_at' => 'immutable_datetime'];
    }
}
