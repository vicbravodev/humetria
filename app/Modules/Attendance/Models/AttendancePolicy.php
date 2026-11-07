<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How a company's people may mark (criterios 4.1 to 4.4) and how long their records are kept (criterio 7.1, at least 12
 * months). One per company; the database defaults are the ones the user chose.
 *
 * @property string $id
 * @property string $tenant_id
 * @property OutOfZoneRule $out_of_zone
 * @property int $max_accuracy_m
 * @property bool $require_qr_onsite
 * @property bool $trusted_may_use_browser
 * @property int $retention_months
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'out_of_zone', 'max_accuracy_m', 'require_qr_onsite', 'trusted_may_use_browser', 'retention_months'])]
#[UseFactory(AttendancePolicyFactory::class)]
final class AttendancePolicy extends Model
{
    /** @use HasFactory<AttendancePolicyFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['out_of_zone' => OutOfZoneRule::class, 'max_accuracy_m' => 'integer', 'require_qr_onsite' => 'boolean', 'trusted_may_use_browser' => 'boolean', 'retention_months' => 'integer'];
    }
}
