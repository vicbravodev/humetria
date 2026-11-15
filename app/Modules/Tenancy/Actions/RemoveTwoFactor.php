<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Turns off a person's two-factor so they can sign in again; the superadmin does it from the console, and it is audited. */
final readonly class RemoveTwoFactor
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(User $user, User $by): void
    {
        DB::transaction(function () use ($user, $by): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            Log::info('user.two_factor_removed', ['user_id' => $by->id, 'target_user_id' => $user->id]);
            $this->recordAudit->handle('user.two_factor_removed', $user);
        });
    }
}
