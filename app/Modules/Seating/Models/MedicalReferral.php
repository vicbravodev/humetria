<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\MedicalReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RH or the Comisión recorded that a person was referred to medical attention (Quinta J), or that they were evaluated and no referral was needed. The note carries no clinical detail and never reaches a log or the audit trail.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $work_center_id
 * @property bool $referred
 * @property CarbonImmutable $referred_on
 * @property string|null $note
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'work_center_id', 'referred', 'referred_on', 'note', 'created_by'])]
#[UseFactory(MedicalReferralFactory::class)]
final class MedicalReferral extends Model
{
    /** @use HasFactory<MedicalReferralFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['referred' => 'boolean', 'referred_on' => 'immutable_date'];
    }
}
