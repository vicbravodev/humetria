<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Data\NewAccountData;
use App\Modules\Tenancy\Enums\InvitationStatus;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Exceptions\InvitationForAnotherAccount;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class AcceptInvitation
{
    public function __construct(private RecordAudit $recordAudit, private CompanyRoles $roles) {}

    /**
     * Gives the accepter the invited role in the company (the owner one replaces the previous owner), creating their
     * account if they have none, and uses up the invitation.
     * The first owner of a company that has not started starts its trial. Signing in is the caller's job.
     *
     * @throws InvitationNoLongerValid when the invitation stopped being pending before it could be locked.
     * @throws InvitationForAnotherAccount when an existing account is not the one the invitation was sent to.
     */
    public function handle(TenantInvitation $invitation, User|NewAccountData $accepter): User
    {
        // Only the invited email joins: whoever holds the link can never hand the company to another account.
        if ($accepter instanceof User && Str::lower($accepter->email) !== $invitation->email) {
            throw new InvitationForAnotherAccount;
        }

        return DB::transaction(function () use ($invitation, $accepter): User {
            // Locked so two acceptances of the same link cannot both pass the check.
            $locked = TenantInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            // A resend that committed since the link was looked up replaced its token: that link no longer works.
            if ($locked->token_hash !== $invitation->token_hash) {
                throw new InvitationNoLongerValid;
            }

            $invitation = $locked;

            if ($invitation->status() !== InvitationStatus::Pending) {
                throw new InvitationNoLongerValid;
            }

            // Locked too: its status decides whether the trial starts.
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($invitation->tenant_id);
            $user = $accepter instanceof User ? $accepter : $this->createUser($invitation->email, $accepter);

            $previousOwnerId = $invitation->role === TenantRole::Owner ? $this->makeOwner($tenant, $user) : null;

            $invitation->update(['accepted_at' => now()]);

            if ($invitation->role !== TenantRole::Owner) {
                // Someone who got access in the meantime keeps the role they have.
                if ($tenant->memberships()->firstOrCreate(['user_id' => $user->id])->wasRecentlyCreated) {
                    $this->roles->assign($user, $tenant, $invitation->role);
                }
            }

            // Only the owner's first access starts the trial; by the time the owner invites anyone, it has started.
            $startsTrial = $invitation->role === TenantRole::Owner && $tenant->status() === TenantStatus::NotStarted;
            $changesOwner = is_int($previousOwnerId) && $previousOwnerId !== $user->id;

            if ($startsTrial) {
                $tenant->update(['trial_started_at' => now()]);
            }

            // The accepter is the actor and the company is the tenant of what follows. Ids only, never the email or name.
            Context::scope(function () use ($invitation, $tenant, $user, $startsTrial, $changesOwner, $previousOwnerId): void {
                Log::info('tenant_invitation.accepted', [
                    'tenant_id' => $tenant->id,
                    'invitation_id' => $invitation->id,
                    'user_id' => $user->id,
                ]);
                $this->recordAudit->handle('tenant_invitation.accepted', $invitation);

                if ($changesOwner) {
                    $change = ['previous_user_id' => $previousOwnerId, 'user_id' => $user->id];
                    Log::info('tenant.owner_changed', ['tenant_id' => $tenant->id, ...$change]);
                    $this->recordAudit->handle('tenant.owner_changed', $tenant, $change);
                }

                if ($startsTrial) {
                    Log::info('tenant.trial_started', ['tenant_id' => $tenant->id]);
                    $this->recordAudit->handle('tenant.trial_started', $tenant);
                }
            }, ['user_id' => $user->id, 'tenant_id' => $tenant->id]);

            return $user;
        });
    }

    /**
     * The company has one owner: the new one takes the membership over, so the previous one loses access to it.
     * A member who becomes the owner leaves their former role, keeping a single membership.
     *
     * @return int|null the previous owner's user id.
     */
    private function makeOwner(Tenant $tenant, User $user): ?int
    {
        $previous = $tenant->ownerMembership()->with('user')->first();

        if ($previous !== null && $previous->user_id !== $user->id) {
            if ($previous->user !== null) {
                $this->roles->remove($previous->user, $tenant);
            }

            $previous->delete();
        }

        $tenant->memberships()->firstOrCreate(['user_id' => $user->id]);
        // Replaces the role a member had: a person holds one role per company.
        $this->roles->assign($user, $tenant, TenantRole::Owner);

        return $previous?->user_id;
    }

    private function createUser(string $email, NewAccountData $data): User
    {
        // The email is verified: the person reached this point through the link sent to it.
        return User::query()->forceCreate([
            'name' => $data->name,
            'email' => $email,
            'password' => $data->password,
            'email_verified_at' => now(),
        ]);
    }
}
