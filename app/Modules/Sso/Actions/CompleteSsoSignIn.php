<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Sso\Exceptions\SsoSignInFailed;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Sso\Support\IdentityClaims;
use App\Modules\Tenancy\Contracts\MemberLookup;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns what a provider vouched for into a person who may sign in: an email the provider verified, at a domain the company
 * proved, of someone who is already a member of that company. It never creates an account, and never lets platform staff in.
 */
final readonly class CompleteSsoSignIn
{
    public function __construct(private MemberLookup $members, private RecordAudit $audit) {}

    /**
     * @throws SsoSignInFailed
     */
    public function handle(string $tenantId, IdentityClaims $identity): User
    {
        $email = $identity->email;

        if ($email === null || ! str_contains($email, '@')) {
            throw new SsoSignInFailed('no_email');
        }

        if (! $identity->emailVerified) {
            throw new SsoSignInFailed('email_not_verified');
        }

        $proven = SsoDomain::query()->where('tenant_id', $tenantId)->where('domain', Str::lower(Str::afterLast($email, '@')))->whereNotNull('verified_at')->exists();

        if (! $proven) {
            throw new SsoSignInFailed('domain_not_proven');
        }

        $userId = $this->members->userIdByEmail($tenantId, $email) ?? throw new SsoSignInFailed('no_member');
        $user = User::query()->findOrFail($userId);

        if ($user->isStaff()) {
            throw new SsoSignInFailed('staff');
        }

        Context::add('user_id', $user->id);
        Log::info('sso.signed_in', ['tenant_id' => $tenantId, 'user_id' => $user->id]);
        $this->audit->handle('sso.signed_in', null, ['tenant_id' => $tenantId]);

        return $user;
    }
}
