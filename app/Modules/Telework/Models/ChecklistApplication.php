<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\Section;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A checklist asked of a person for one workplace: a section of one published version. Answered once, whole.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $workplace_id
 * @property string $checklist_id
 * @property Section $section
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $submitted_at
 * @property string|null $consent_id
 * @property CarbonImmutable|null $consent_accepted_at
 * @property string|null $special_needs
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'workplace_id', 'checklist_id', 'section', 'requested_at', 'submitted_at', 'consent_id', 'consent_accepted_at', 'special_needs'])]
#[UseFactory(ChecklistApplicationFactory::class)]
final class ChecklistApplication extends Model
{
    /** @use HasFactory<ChecklistApplicationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * The applications of a teleworker's workplaces still in use: the only ones their portal may open.
     *
     * @param  Builder<ChecklistApplication>  $query
     */
    protected function scopeOwnedBy(Builder $query, string $teleworkerId): void
    {
        $query->whereHas('workplace', fn (Builder $workplaces) => $workplaces->where('teleworker_id', $teleworkerId)->whereNull('retired_at'));
    }

    /**
     * @return BelongsTo<Workplace, $this>
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => Section::class,
            'requested_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'consent_accepted_at' => 'immutable_datetime',
        ];
    }
}
