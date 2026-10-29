<?php

declare(strict_types=1);

namespace App\Http;

use App\Actions\RecordAudit;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Who, if anyone, is impersonating the logged-in user. The superadmin's id
 * lives in the session; it is the only trace that tells a superadmin's
 * actions apart from the owner's they are acting as.
 */
final readonly class Impersonation
{
    public const string SESSION_KEY = 'impersonator_id';

    public function __construct(
        private Session $session,
        private StatefulGuard $guard,
        private RecordAudit $recordAudit,
    ) {}

    public function impersonatorId(): ?int
    {
        $id = $this->session->get(self::SESSION_KEY);

        return is_int($id) ? $id : null;
    }

    public function isActive(): bool
    {
        return $this->impersonatorId() !== null;
    }

    /**
     * Signs in as the target, recorded first as the superadmin. The tenant the console row
     * belongs to, if any, comes from the Context. Logging in gives the session a new id.
     */
    public function start(User $impersonator, User $target): void
    {
        Context::scope(function () use ($target): void {
            $this->recordAudit->handle('impersonation.started', $target, ['tenant_id' => Context::get('tenant_id')]);
            Log::info('impersonation.started', ['subject_id' => $target->id]);
        }, ['user_id' => $impersonator->id, 'impersonator_id' => $impersonator->id]);

        $this->guard->login($target);
        $this->session->put(self::SESSION_KEY, $impersonator->id);
    }

    /**
     * Ends the impersonation, recorded while still the owner. Gives the superadmin back, or
     * signs out and gives null when they are gone or no longer a superadmin.
     */
    public function stop(): ?User
    {
        $impersonatorId = $this->impersonatorId();
        $ownerId = $this->guard->id();

        Context::scope(function () use ($ownerId): void {
            $this->recordAudit->handle('impersonation.stopped', $this->guard->user());
            Log::info('impersonation.stopped', ['subject_id' => $ownerId]);
        }, ['user_id' => $ownerId, 'impersonator_id' => $impersonatorId]);

        $this->session->forget(self::SESSION_KEY);
        $impersonator = User::query()->find($impersonatorId);

        if ($impersonator === null || ! $impersonator->hasRole(User::SUPERADMIN)) {
            $this->guard->logout();
            $this->session->invalidate();
            $this->session->regenerateToken();

            return null;
        }

        $this->guard->login($impersonator);

        return $impersonator;
    }
}
