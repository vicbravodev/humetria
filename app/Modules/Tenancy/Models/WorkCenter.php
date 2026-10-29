<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Enums\MexicanState;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A place where the company's people work. NOM-035 applies per work center (num. 4.4).
 * Every query sees only the current company's work centers; with no company resolved, it fails.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $street
 * @property string $exterior_number
 * @property string|null $interior_number
 * @property string $neighborhood
 * @property string $postal_code
 * @property string $municipality
 * @property MexicanState $state
 * @property string $main_activity
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'name', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'municipality', 'state', 'main_activity', 'archived_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(WorkCenterFactory::class)]
final class WorkCenter extends Model
{
    /** @use HasFactory<WorkCenterFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * The address in one line, as it is written in Mexico: "Reforma 222 int. 4, col. Juárez, C.P. 06600, Cuauhtémoc, Ciudad de México".
     */
    public function address(): string
    {
        $number = $this->interior_number === null ? $this->exterior_number : "{$this->exterior_number} int. {$this->interior_number}";

        return "{$this->street} {$number}, col. {$this->neighborhood}, C.P. {$this->postal_code}, {$this->municipality}, {$this->state->label()}";
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected static function booted(): void
    {
        self::addGlobalScope('tenant', function (Builder $query): void {
            $query->where($query->qualifyColumn('tenant_id'), resolve(CurrentTenant::class)->id());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => MexicanState::class,
            'archived_at' => 'immutable_datetime',
        ];
    }
}
