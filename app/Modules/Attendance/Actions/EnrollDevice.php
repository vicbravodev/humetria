<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Notifications\DeviceLinked;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Links a person's device with their enrollment link: verifies the key the device created, then, at once, uses up the link,
 * saves the key and records the acceptance of the agreement the person read. False, changing nothing, when the link
 * does not work (used, replaced, expired, unknown, of another company) or its person left.
 */
final readonly class EnrollDevice
{
    public function __construct(private Roster $roster, private Companies $companies, private WorkerKeys $keys, private AcceptAgreement $accept) {}

    /**
     * @param  array<mixed>  $credential  the browser's PublicKeyCredential as JSON
     *
     * @throws InvalidCredential when the key does not verify (the link stays for another try)
     * @throws AgreementOutdated when the person read a version that is no longer the current one
     */
    public function handle(string $token, int $agreementVersion, array $credential, string $label): bool
    {
        $link = EnrollmentLink::findPending($token);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        if ($link === null || $person === null || ! $person->active) {
            return false;
        }

        $key = $this->keys->verifyRegistration($person->id, $credential) ?? throw new InvalidCredential;

        $credentialId = DB::transaction(function () use ($link, $person, $key, $label, $agreementVersion): ?string {
            $now = CarbonImmutable::now();

            // One conditional UPDATE: of two requests with the same link at once, only one finds it still pending.
            $used = EnrollmentLink::query()->whereKey($link->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', $now)
                ->update(['used_at' => $now]);

            if ($used !== 1) {
                return null;
            }

            // A key already saved: the same refusal as any key that does not verify.
            $credentialId = $this->keys->save($person->id, $key, $label, 'issued_link') ?? throw new InvalidCredential;

            $this->accept->handle($person->id, $credentialId, $agreementVersion);

            return $credentialId;
        });

        if ($credentialId !== null) {
            Log::info('attendance.device_enrolled', ['key_id' => $credentialId, 'employee_id' => $person->id]);

            // After the commit: the person learns of every device linked to their record, so they notice one that was not them.
            if ($person->email !== null) {
                Notification::route('mail', $person->email)->notify(new DeviceLinked($label, CarbonImmutable::now(), $person->name, $this->companies->current()));
            }
        }

        return $credentialId !== null;
    }
}
