<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saves the device key a person's phone made (plan B of the offline marks). The possession proof and the public key were checked
 * before, by the controller. A person has at most `worker_access.device_keys_max` in use, counted under a lock of the person
 * (two registrations at once do not pass the limit together): a fourth replaces the least used one (`replaced`) and never
 * answers 409, so whoever cleared the phone's data or reinstalled is not left out. The audit trail and the log carry ids only,
 * never the public key.
 */
final readonly class RegisterDeviceKey
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $employeeId, string $publicKey, string $label): WorkerDeviceKey
    {
        [$key, $replacedId] = DB::transaction(function () use ($employeeId, $publicKey, $label): array {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["worker_device_keys:{$employeeId}"]);

            $active = WorkerDeviceKey::query()->where('employee_id', $employeeId)->whereNull('revoked_at');
            $replaced = null;

            if ($active->count() >= config()->integer('worker_access.device_keys_max')) {
                $replaced = (clone $active)->orderByRaw('coalesce(last_used_at, created_at)')->orderBy('created_at')->first();
                $replaced?->forceFill(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => 'replaced'])->save();
            }

            $key = WorkerDeviceKey::query()->create([
                'tenant_id' => $this->tenant->id(), 'employee_id' => $employeeId, 'public_key' => $publicKey, 'label' => $label,
                'last_used_at' => CarbonImmutable::now(),
            ]);
            $metadata = array_filter(['employee_id' => $employeeId, 'replaced_id' => $replaced?->id]);
            $this->audit->handle('worker_access.device_key_registered', $key, $metadata);

            return [$key, $replaced?->id];
        });

        Log::info('worker_access.device_key_registered', array_filter(['device_key_id' => $key->id, 'employee_id' => $employeeId, 'replaced_id' => $replacedId]));

        return $key;
    }
}
