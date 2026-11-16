<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // Whoever held the account before the reset (a squatter, a stolen device) loses every way back in.
        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => $input['password'],
            ])->save();

            $user->passkeys()->delete();
            DB::table(Config::string('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });

        Log::info('user.password_reset', ['user_id' => $user->id]);
    }
}
