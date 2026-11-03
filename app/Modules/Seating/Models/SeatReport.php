<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Seating\Enums\ReportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The report of a damaged seat. From the portal it is tied to a center and a position and to nobody: the person who reports does not have to be known. Open until RH resolves it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string|null $seat_id
 * @property string|null $position_id
 * @property string $description
 * @property ReportStatus $status
 * @property string|null $resolution
 * @property CarbonImmutable|null $resolved_at
 * @property bool $reported_via_portal
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'seat_id', 'position_id', 'description', 'status', 'resolution', 'resolved_at', 'reported_via_portal'])]
#[UseFactory(SeatReportFactory::class)]
final class SeatReport extends Model
{
    /** @use HasFactory<SeatReportFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ReportStatus::class, 'resolved_at' => 'immutable_datetime', 'reported_via_portal' => 'boolean'];
    }
}
