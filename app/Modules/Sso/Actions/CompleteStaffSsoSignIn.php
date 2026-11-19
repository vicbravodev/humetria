<?php

declare(strict_types=1);

namespace App\Modules\Sso\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Sso\Exceptions\SsoSignInFailed;
use App\Modules\Sso\Support\IdentityClaims;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The staff of Humetría coming in with the platform's provider: a verified email at the staff domain, of an account that
 * already exists and holds a platform role. It never creates accounts and never lets in anyone who is not staff.
 */
final readonly class CompleteStaffSsoSignIn
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws SsoSignInFailed
     */
    public function handle(IdentityClaims $identity): User
    {
        $email = $identity->email === null ? null : Str::lower($identity->email);

        if ($email === null || ! str_contains($email, '@')) {
            throw new SsoSignInFailed('no_email');
        }

        if (! $identity->emailVerified) {
            throw new SsoSignInFailed('email_not_verified');
        }

        if (Str::afterLast($email, '@') !== Str::lower(config()->string('sso.staff.domain'))) {
            throw new SsoSignInFailed('domain_not_ours');
        }

        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null || ! $user->isStaff()) {
            throw new SsoSignInFailed('not_staff');
        }

        Context::add('user_id', $user->id);
        Log::info('sso.staff_signed_in', ['user_id' => $user->id]);
        $this->audit->handle('sso.staff_signed_in');

        return $user;
    }
}
