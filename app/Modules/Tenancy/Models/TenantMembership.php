<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user who belongs to a company. Their role there is kept by Spatie (see CompanyRoles).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'user_id'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(TenantMembershipFactory::class)]
final class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
