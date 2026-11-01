<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    /** Postgres SQLSTATEs of a row other rows still point to: `restrict_violation` (ON DELETE RESTRICT) and `foreign_key_violation`. */
    private const array STILL_REFERENCED = ['23001', '23503'];

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile. An account that records the law keeps name (a review it decided, a campaign it created:
     * NOM-037 10.4, NOM-035 10.4) cannot be deleted: the foreign keys refuse it, and the user stays signed in and is told
     * why. Signing out happens only once the account is gone.
     *
     * @throws ValidationException
     */
    public function destroy(ProfileDeleteRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            // Its own transaction: the refused delete is rolled back and nothing else is touched.
            DB::transaction(fn () => $user->delete());
        } catch (QueryException $refused) {
            if (! in_array($refused->getCode(), self::STILL_REFERENCED, true)) {
                throw $refused;
            }

            Log::info('account.deletion_blocked', ['user_id' => $user->id]);

            throw ValidationException::withMessages(['password' => 'No puedes borrar tu cuenta: hay registros que la ley obliga a conservar a tu nombre. Pide a la empresa que te quite el acceso.']);
        }

        Log::info('account.deleted', ['user_id' => $user->id]);

        // Not `logout()`: it would save a new remember token, inserting the deleted user again.
        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
