<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Exceptions\AgreementOutdated;
use App\Modules\Attendance\Exceptions\InvalidCredential;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Contracts\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Links a person's device with their enrollment link: verifies the key the device created, then, at once, uses up the link,
 * saves the device and records the acceptance of the agreement the person read. Null, changing nothing, when the link
 * does not work (used, replaced, expired, unknown, of another company) or its person left.
 */
final readonly class EnrollDevice
{
    public function __construct(private Roster $roster, private WebAuthnCeremony $ceremony, private AcceptAgreement $accept, private RecordAudit $audit) {}

    /**
     * @param  array<mixed>  $credential  the browser's PublicKeyCredential as JSON
     *
     * @throws InvalidCredential when the key does not verify (the link stays for another try)
     * @throws AgreementOutdated when the person read a version that is no longer the current one
     */
    public function handle(string $token, int $agreementVersion, array $credential, string $label): ?AttendanceDevice
    {
        $link = EnrollmentLink::findPending($token);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        if ($link === null || $person === null || ! $person->active) {
            return null;
        }

        $key = $this->ceremony->verifyRegistration($person->id, $credential);

        $device = DB::transaction(function () use ($link, $person, $key, $label, $agreementVersion): ?AttendanceDevice {
            $now = CarbonImmutable::now();

            // One conditional UPDATE: of two requests with the same link at once, only one finds it still pending.
            $used = EnrollmentLink::query()->whereKey($link->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', $now)
                ->update(['used_at' => $now]);

            if ($used !== 1) {
                return null;
            }

            try {
                $device = AttendanceDevice::query()->create([
                    'tenant_id' => $link->tenant_id,
                    'employee_id' => $person->id,
                    'credential_id' => $key->credentialId,
                    'public_key' => $key->publicKey,
                    'sign_count' => $key->signCount,
                    'label' => $label,
                    'enrolled_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A key already linked: the same refusal as any key that does not verify.
                throw new InvalidCredential;
            }

            $this->accept->handle($person->id, $device->id, $agreementVersion);
            $this->audit->handle('attendance.device_enrolled', $device, ['employee_id' => $person->id]);

            return $device;
        });

        if ($device !== null) {
            Log::info('attendance.device_enrolled', ['device_id' => $device->id, 'employee_id' => $person->id]);
        }

        return $device;
    }
}
