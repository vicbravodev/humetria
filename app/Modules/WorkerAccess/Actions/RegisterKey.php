<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Data\CredentialData;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Exceptions\TooManyKeys;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\KeyRegistered;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Saves a key a person's device made in their session (CUJ 1): only a session opened with a link RH issued or with another key
 * may, and only while the person has fewer keys in use than allowed (counted under a lock of the person, so two at once do not
 * pass the limit together). The key goes to the audit trail and the log, and the person is told by email, with «No fui yo»
 * on a signed address that revokes it. Null, saving nothing, when that key is already saved.
 */
final readonly class RegisterKey
{
    public function __construct(private RegisterCredential $register, private Roster $roster, private Companies $companies) {}

    /**
     * @throws TooManyKeys when the person already has the most keys allowed
     * @throws InvalidArgumentException when the session entered with a link the person asked for
     */
    public function handle(string $employeeId, CredentialData $key, string $label, EntryVia $via): ?WorkerCredential
    {
        if ($via === EntryVia::RequestedLink) {
            throw new InvalidArgumentException('A session opened with a requested link may not register a key.');
        }

        $credential = DB::transaction(function () use ($employeeId, $key, $label, $via): ?WorkerCredential {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["worker_credentials:{$employeeId}"]);

            if (KeyAllowance::full($employeeId)) {
                throw new TooManyKeys;
            }

            return $this->register->handle($employeeId, $key, $label, $via === EntryVia::IssuedLink ? 'issued_link' : 'session');
        });

        if ($credential !== null) {
            Log::info('worker_access.key_registered', ['key_id' => $credential->id, 'employee_id' => $employeeId, 'via' => $via->value]);
            $this->tell($credential);
        }

        return $credential;
    }

    /** The person learns of every key registered to them, so they notice one that was not them. */
    private function tell(WorkerCredential $credential): void
    {
        $person = $this->roster->find($credential->employee_id);
        $company = $this->companies->current();

        if ($person?->email === null) {
            return;
        }

        $notMe = rtrim(config()->string('app.url'), '/').URL::temporarySignedRoute('worker.not-me.show', CarbonImmutable::now()->addDays(30), ['tenant' => $company->slug, 'credential' => $credential->id], absolute: false);
        Notification::route('mail', $person->email)->notify(new KeyRegistered($credential->label, $credential->registered_at, $person->name, $company, $notMe));
    }
}
