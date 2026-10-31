<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\PortalLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The personal portal link of an employee: one at a time, only its hash is kept, issuing a new one replaces it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $token_hash
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'token_hash'])]
#[UseFactory(PortalLinkFactory::class)]
final class PortalLink extends Model
{
    /** @use HasFactory<PortalLinkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
