<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Tenancy\Enums\InvitationStatus;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to join a company. Only the hash of its token is stored.
 *
 * @property string $id
 * @property string $tenant_id
 * @property TenantRole $role
 * @property string $name
 * @property string $email
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $invited_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'role', 'name', 'email', 'token_hash', 'expires_at', 'accepted_at', 'cancelled_at', 'invited_by'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(TenantInvitationFactory::class)]
final class TenantInvitation extends Model
{
    /** @use HasFactory<TenantInvitationFactory> */
    use HasFactory, HasVersion4Uuids;

    public const int VALID_DAYS = 7;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Re-reads the invitation under lock, inside the caller's transaction, so what was checked on a copy read earlier
     * cannot have been changed by an acceptance, a cancellation or a resend that committed in between.
     *
     * @throws InvitationNoLongerValid when it was accepted or cancelled meanwhile.
     */
    public static function lockOpen(self $invitation): self
    {
        $locked = self::query()->lockForUpdate()->findOrFail($invitation->id);

        if ($locked->accepted_at !== null || $locked->cancelled_at !== null) {
            throw new InvitationNoLongerValid;
        }

        return $locked;
    }

    public function status(): InvitationStatus
    {
        if ($this->accepted_at !== null) {
            return InvitationStatus::Accepted;
        }

        if ($this->cancelled_at !== null) {
            return InvitationStatus::Cancelled;
        }

        return now()->lessThan($this->expires_at) ? InvitationStatus::Pending : InvitationStatus::Expired;
    }

    /**
     * @param  Builder<TenantInvitation>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Who sent it, named on the link page; null when it was sent from the console without a recorded sender.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
