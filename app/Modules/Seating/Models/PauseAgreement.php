<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\PauseAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What was agreed with the people of a position about their pauses (criterio C-14): how many, how long, when and how it was reached. A new agreement is a new row; the latest counts.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string $position_id
 * @property int $pauses_per_shift
 * @property int $minutes_each
 * @property CarbonImmutable $agreed_on
 * @property string $how_agreed
 * @property string|null $evidence_path
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'position_id', 'pauses_per_shift', 'minutes_each', 'agreed_on', 'how_agreed', 'evidence_path', 'created_by'])]
#[UseFactory(PauseAgreementFactory::class)]
final class PauseAgreement extends Model
{
    /** @use HasFactory<PauseAgreementFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pauses_per_shift' => 'integer', 'minutes_each' => 'integer', 'agreed_on' => 'immutable_date'];
    }
}
