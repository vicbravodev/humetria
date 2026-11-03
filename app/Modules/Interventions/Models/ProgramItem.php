<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use Carbon\CarbonImmutable;
use Database\Factories\Interventions\ProgramItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of the Programa: the final score, a category or a domain it attends (criterio C-30), with its label frozen, the
 * highest level that led to it (none when HR added it), the analysis HR wrote (II.4 and III.4) and the follow-up evaluation
 * of the row (8.4 e). It holds no count of questionnaires.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $program_id
 * @property ItemScope $scope
 * @property string $scope_key
 * @property string $label
 * @property string|null $category_key
 * @property ItemOrigin $origin
 * @property RiskLevel|null $highest_level
 * @property string|null $analysis
 * @property CarbonImmutable|null $follow_up_due_on
 * @property CarbonImmutable|null $follow_up_done_on
 * @property string|null $follow_up_result
 * @property string|null $follow_up_campaign_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('intervention_items')]
#[Fillable(['tenant_id', 'program_id', 'scope', 'scope_key', 'label', 'category_key', 'origin', 'highest_level', 'analysis', 'follow_up_due_on', 'follow_up_done_on', 'follow_up_result', 'follow_up_campaign_id'])]
#[UseFactory(ProgramItemFactory::class)]
final class ProgramItem extends Model
{
    /** @use HasFactory<ProgramItemFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ItemScope::class,
            'origin' => ItemOrigin::class,
            'highest_level' => RiskLevel::class,
            'follow_up_due_on' => 'immutable_date',
            'follow_up_done_on' => 'immutable_date',
        ];
    }
}
