<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A version of the company's agreement on the electronic record (criterio 5.1), the text a person accepts when they link a
 * device. Append-only: a Postgres trigger rejects UPDATE, DELETE and TRUNCATE, so an accepted text never changes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $version
 * @property string $body
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'version', 'body'])]
final class AttendanceAgreement extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
