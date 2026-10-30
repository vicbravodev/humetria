<?php

declare(strict_types=1);

namespace App\Modules\Results\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Results\ReportDetailsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What HR fills in for an evaluation's report (num. 7.7 b, c, f, g, h). The cédula is optional: "en su caso".
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $objective
 * @property string $activities
 * @property string $conclusions
 * @property string $recommendations
 * @property string $responsible_name
 * @property string|null $responsible_license
 * @property int $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('result_reports')]
#[Fillable(['tenant_id', 'campaign_id', 'objective', 'activities', 'conclusions', 'recommendations', 'responsible_name', 'responsible_license', 'updated_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ReportDetailsFactory::class)]
final class ReportDetails extends Model
{
    /** @use HasFactory<ReportDetailsFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;
}
