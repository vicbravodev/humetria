<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place where a person teleworks. A retired one (the person moved) is kept.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $teleworker_id
 * @property string $street
 * @property string $exterior_number
 * @property string|null $interior_number
 * @property string $neighborhood
 * @property string $postal_code
 * @property string $municipality
 * @property string $state
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'teleworker_id', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'municipality', 'state', 'retired_at'])]
#[UseFactory(WorkplaceFactory::class)]
final class Workplace extends Model
{
    /** @use HasFactory<WorkplaceFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Teleworker, $this>
     */
    public function teleworker(): BelongsTo
    {
        return $this->belongsTo(Teleworker::class);
    }

    /**
     * @return HasMany<ChecklistApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(ChecklistApplication::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }
}
