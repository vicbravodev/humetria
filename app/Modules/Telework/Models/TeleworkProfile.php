<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TeleworkProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a work center declares for NOM-037 (board V2-Aplica): whether someone works away from it more than 40 % of their
 * time (LFT 330-A), with who declared it and when. One per center; declaring again replaces it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property bool $has_teleworkers
 * @property int $declared_by
 * @property CarbonImmutable $declared_on
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'has_teleworkers', 'declared_by', 'declared_on'])]
#[UseFactory(TeleworkProfileFactory::class)]
final class TeleworkProfile extends Model
{
    /** @use HasFactory<TeleworkProfileFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['has_teleworkers' => 'boolean', 'declared_on' => 'immutable_date'];
    }
}
