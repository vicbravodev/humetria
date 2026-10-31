<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One version of the consent text for health data and photos. Never edited or deleted: later versions replace it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $version
 * @property string $text
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'version', 'text', 'created_by'])]
final class Consent extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    public const ?string UPDATED_AT = null;

    protected $table = 'telework_consents';
}
