<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\SafetyCommitteeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The Comisión de seguridad e higiene of a work center: the date of its constitution act and, optionally, the act's PDF.
 * Who sits on it is in CommitteeAssignment. One per center; never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property CarbonImmutable $constituted_on
 * @property string|null $act_path
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'constituted_on', 'act_path', 'created_by'])]
#[UseFactory(SafetyCommitteeFactory::class)]
final class SafetyCommittee extends Model
{
    /** @use HasFactory<SafetyCommitteeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['constituted_on' => 'immutable_date'];
    }
}
