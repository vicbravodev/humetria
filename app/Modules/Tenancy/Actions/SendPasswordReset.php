<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/** Sends a person the link to create a new password, for whoever cannot get in. The token is never recorded. */
final readonly class SendPasswordReset
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * @return bool false when a link went out a moment ago: the broker throttles them and nothing was sent
     */
    public function handle(User $user, User $by): bool
    {
        if (Password::broker()->sendResetLink(['email' => $user->email]) !== Password::RESET_LINK_SENT) {
            return false;
        }

        Log::info('user.password_reset_sent', ['user_id' => $by->id, 'target_user_id' => $user->id]);
        $this->recordAudit->handle('user.password_reset_sent', $user);

        return true;
    }
}
