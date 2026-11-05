<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\WorkRiskPremiumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The IMSS work risk premium of the company from a date on (spec, decision 9): one rate per company and vigencia. The
 * rate is rate_scaled / 10^7 (SCALE), so 7.58875 % is 758875; the law keeps it between 0.5 % and 15 %.
 *
 * @property string $id
 * @property string $tenant_id
 * @property CarbonImmutable $effective_from
 * @property int $rate_scaled
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'effective_from', 'rate_scaled'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(WorkRiskPremiumFactory::class)]
final class WorkRiskPremium extends Model
{
    /** @use HasFactory<WorkRiskPremiumFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** The rate is rate_scaled / 10^SCALE. */
    public const int SCALE = 7;

    // Laravel would pluralize "premium" to "premia".
    protected $table = 'work_risk_premiums';

    public const int MINIMUM = 50_000;

    public const int MAXIMUM = 1_500_000;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_date',
            'rate_scaled' => 'integer',
        ];
    }
}
