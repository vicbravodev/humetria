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
use Illuminate\Database\Query\Builder as QueryBuilder;
use Spatie\Permission\Models\Role;

/**
 * An invitation to join a company. Only the hash of its token is stored.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int|null $role_id null once its role was deleted, which only a used or cancelled invitation allows.
 * @property list<string> $extra_permissions given besides the role when it is accepted.
 * @property-read Role|null $role
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
#[Fillable(['tenant_id', 'role_id', 'extra_permissions', 'name', 'email', 'token_hash', 'expires_at', 'accepted_at', 'cancelled_at', 'invited_by'])] // @pest-mutate-ignore: RemoveArrayItem
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

    /** Whether it invites the company's owner (from the superadmin console) rather than a member. */
    public function invitesOwner(): bool
    {
        return $this->role?->name === TenantRole::Owner->value && $this->role->getAttribute('tenant_id') === null;
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
     * Invitations to own the company, or (`$owner` false) to join it as a member: a closed one whose role was deleted
     * since has none, and was a member's (the owner's role is never deleted).
     *
     * @param  Builder<TenantInvitation>  $query
     */
    public function scopeForOwner(Builder $query, bool $owner = true): void
    {
        $owner
            ? $query->whereIn('role_id', self::ownerRole())
            : $query->where(fn (Builder $member) => $member->whereNull('role_id')->orWhereNotIn('role_id', self::ownerRole()));
    }

    /**
     * The role it gives: a template or one of the company's own.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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

    /** The owner template's id. */
    private static function ownerRole(): QueryBuilder
    {
        return Role::query()->toBase()->select('id')->where('name', TenantRole::Owner->value)->whereNull('tenant_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extra_permissions' => 'array',
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
