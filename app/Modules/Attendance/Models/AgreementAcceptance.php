<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * That a person accepted a version of the agreement, on the device they linked, and when (criterio 5.1): half of the
 * evidence that the record was agreed (criterio 1.3). Append-only.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $agreement_id
 * @property string $employee_id
 * @property string $device_id
 * @property CarbonImmutable $accepted_at
 */
#[Fillable(['tenant_id', 'agreement_id', 'employee_id', 'device_id', 'accepted_at'])]
#[Table('attendance_agreement_acceptances')]
final class AgreementAcceptance extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }
}
