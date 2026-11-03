<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A seat, or a group of equal seats, of a work center: at a position's workstation or in an area designated for the rests.
 * Never deleted (criterio C-17): one out of service says so.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string|null $position_id
 * @property string|null $location
 * @property SeatKind $kind
 * @property SeatUse $use
 * @property int $quantity
 * @property bool $backrest
 * @property bool $height
 * @property bool $lumbar_support
 * @property bool $adjustable
 * @property bool $ergonomic
 * @property bool $mobility
 * @property bool $stability
 * @property bool $armrests
 * @property bool $footrest
 * @property SeatCondition $condition
 * @property bool $signposted
 * @property string|null $photo_path
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'position_id', 'location', 'kind', 'use', 'quantity', 'backrest', 'height', 'lumbar_support', 'adjustable', 'ergonomic', 'mobility', 'stability', 'armrests', 'footrest', 'condition', 'signposted', 'photo_path'])]
#[UseFactory(SeatFactory::class)]
final class Seat extends Model
{
    /** @use HasFactory<SeatFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SeatKind::class,
            'use' => SeatUse::class,
            'condition' => SeatCondition::class,
            'quantity' => 'integer',
            'backrest' => 'boolean', 'height' => 'boolean', 'lumbar_support' => 'boolean', 'adjustable' => 'boolean', 'ergonomic' => 'boolean',
            'mobility' => 'boolean', 'stability' => 'boolean', 'armrests' => 'boolean', 'footrest' => 'boolean',
            'signposted' => 'boolean',
        ];
    }
}
