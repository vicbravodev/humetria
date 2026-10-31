<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * The NOM-037 settings of one company.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $review_period_months
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'review_period_months'])]
final class TeleworkSettings extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;
}
