<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Data\NewAccountData;
use App\Modules\Tenancy\Enums\InvitationStatus;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class AcceptInvitation
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Makes the accepter the company's owner, creating their account if they have none, and uses up the invitation.
     * The first owner of a company that has not started starts its trial. Signing in is the caller's job.
     *
     * @throws InvitationNoLongerValid when the invitation stopped being pending before it could be locked.
     */
    public function handle(TenantInvitation $invitation, User|NewAccountData $accepter): User
    {
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

            $tenant->ownerMembership()->updateOrCreate(['role' => TenantRole::Owner], ['user_id' => $user->id]);
            $invitation->update(['accepted_at' => now()]);

            $startsTrial = $tenant->status() === TenantStatus::NotStarted;

            if ($startsTrial) {
                $tenant->update(['trial_started_at' => now()]);
            }

            // The accepter is the actor and the company is the tenant of what follows. Ids only, never the email or name.
            Context::scope(function () use ($invitation, $tenant, $user, $startsTrial): void {
                Log::info('tenant_invitation.accepted', [
                    'tenant_id' => $tenant->id,
                    'invitation_id' => $invitation->id,
                    'user_id' => $user->id,
                ]);
                $this->recordAudit->handle('tenant_invitation.accepted', $invitation);

                if ($startsTrial) {
                    Log::info('tenant.trial_started', ['tenant_id' => $tenant->id]);
                    $this->recordAudit->handle('tenant.trial_started', $tenant);
                }
            }, ['user_id' => $user->id, 'tenant_id' => $tenant->id]);

            return $user;
        });
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
