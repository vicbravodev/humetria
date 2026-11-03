<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Seating\Enums\Sector;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatingProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a work center declares for Ley Silla: its sector, and that it has no people working standing, with who declared it
 * and when. One per center; declaring again replaces it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property Sector $sector
 * @property bool $no_standing_positions
 * @property int $declared_by
 * @property CarbonImmutable $declared_on
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'sector', 'no_standing_positions', 'declared_by', 'declared_on', 'note'])]
#[UseFactory(SeatingProfileFactory::class)]
final class SeatingProfile extends Model
{
    /** @use HasFactory<SeatingProfileFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sector' => Sector::class, 'no_standing_positions' => 'boolean', 'declared_on' => 'immutable_date'];
    }
}
