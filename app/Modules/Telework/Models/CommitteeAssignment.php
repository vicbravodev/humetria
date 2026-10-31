<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\CommitteeAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A user of the company who sits on the Comisión of a work center. Whoever has one may review that center's workplaces.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'user_id'])]
#[UseFactory(CommitteeAssignmentFactory::class)]
final class CommitteeAssignment extends Model
{
    /** @use HasFactory<CommitteeAssignmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;
}
