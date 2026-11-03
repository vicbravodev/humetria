<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Seating\Enums\RegulationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\InternalRegulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The internal regulation of a work center about the standing-work obligation: its parts and the steps it went through. One per center.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property array{risk_factors: string, pauses: string, seat_location: string, usage_rule: string} $parts
 * @property RegulationStatus $status
 * @property CarbonImmutable|null $signed_on
 * @property CarbonImmutable|null $deposited_on
 * @property string|null $deposit_folio
 * @property CarbonImmutable|null $distributed_on
 * @property string|null $file_path
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'parts', 'status', 'signed_on', 'deposited_on', 'deposit_folio', 'distributed_on', 'file_path', 'created_by'])]
#[UseFactory(InternalRegulationFactory::class)]
final class InternalRegulation extends Model
{
    /** @use HasFactory<InternalRegulationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['parts' => 'array', 'status' => RegulationStatus::class, 'signed_on' => 'immutable_date', 'deposited_on' => 'immutable_date', 'distributed_on' => 'immutable_date'];
    }
}
