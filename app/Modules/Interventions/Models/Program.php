<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Instruments\Data\RiskLevel;
use Carbon\CarbonImmutable;
use Database\Factories\Interventions\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Programa de intervención of a closed evaluation (num. 8.3 and 8.4): one per evaluation, with its rows and actions. It
 * keeps the highest level that led to it and who answers for it; every figure is read live from Results.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property RiskLevel|null $highest_level
 * @property string|null $responsible_name
 * @property int|null $responsible_user_id
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('intervention_programs')]
#[Fillable(['tenant_id', 'campaign_id', 'highest_level', 'responsible_name', 'responsible_user_id', 'created_by'])]
#[UseFactory(ProgramFactory::class)]
final class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<ProgramItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProgramItem::class);
    }

    /**
     * @return HasMany<ProgramAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ProgramAction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['highest_level' => RiskLevel::class];
    }
}
