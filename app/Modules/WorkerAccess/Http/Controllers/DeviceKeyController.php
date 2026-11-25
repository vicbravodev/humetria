<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Actions\RegisterDeviceKey;
use App\Modules\WorkerAccess\Http\Requests\RegisterDeviceKeyRequest;
use App\Modules\WorkerAccess\Http\Requests\RenewPassRequest;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\DeviceKeyCrypto;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use App\Modules\WorkerAccess\Support\RequestWorkerSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;

/**
 * The device key of the app (plan B of the offline marks), JSON only and with the person's session: `options` gives a challenge to
 * prove possession, `store` registers the key the phone made (it signed the 32 raw bytes of the challenge) and answers with a pass
 * of 24 hours, and `pass` renews it when the person opens their start. Registering takes a recent proof (alias `worker.proof`);
 * renewing does not: it only moves the key's last use. The person always comes from the session. Nothing of the key, the challenge,
 * the signature or the pass reaches a log.
 */
final readonly class DeviceKeyController
{
    public function __construct(private RequestWorkerSession $sessions, private CurrentTenant $tenant) {}

    public function options(Request $request): JsonResponse
    {
        $challenge = random_bytes(32);
        Cache::put($this->cacheKey($request, $challenge), true, max(1, config()->integer('worker_access.webauthn.challenge_seconds')));

        return response()->json(['challenge' => Base64UrlSafe::encodeUnpadded($challenge)])->header('Cache-Control', 'no-store, private');
    }

    public function store(RegisterDeviceKeyRequest $request, RegisterDeviceKey $register): JsonResponse
    {
        $person = $this->person();
        $challenge = $this->decode($request->string('challenge')->toString());
        $key = DeviceKeyCrypto::publicKeyFrom($request->string('public_key')->toString());

        // The challenge is taken once: `pull` removes it, so of two answers with the same challenge only the first may pass.
        $given = $challenge !== null && strlen($challenge) === 32 && Cache::pull($this->cacheKey($request, $challenge)) === true;

        if (! $given || $key === null || ! DeviceKeyCrypto::verifies($key, $challenge, $request->string('signature')->toString())) {
            Log::warning('worker_access.device_key_refused', ['employee_id' => $person]);

            return response()->json(['message' => 'No pudimos preparar este teléfono. Intente de nuevo.'], 422)->header('Cache-Control', 'no-store, private');
        }

        $saved = $register->handle($person, $request->string('public_key')->toString(), $request->string('label')->toString());

        return response()->json($this->answer($saved), 201)->header('Cache-Control', 'no-store, private');
    }

    public function pass(RenewPassRequest $request): JsonResponse
    {
        $key = WorkerDeviceKey::query()->where('employee_id', $this->person())->whereNull('revoked_at')->find($request->string('device_key_id')->toString());
        abort_if($key === null, 404);

        $key->forceFill(['last_used_at' => CarbonImmutable::now()])->save();

        return response()->json($this->answer($key))->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{deviceKeyId: string, pass: string, passId: string, passExpiresAt: string}
     */
    private function answer(WorkerDeviceKey $key): array
    {
        $issued = OfflinePasses::issue($key);

        return ['deviceKeyId' => $key->id, 'pass' => $issued['pass'], 'passId' => $issued['passId'], 'passExpiresAt' => $issued['expiresAt']->toIso8601String()];
    }

    /** The person of the session (the route takes it: RequireWorkerSession). */
    private function person(): string
    {
        return ($this->sessions->session() ?? abort(401))->identity->employeeId;
    }

    /** The challenge is of one company and one browser session: neither of them gives it to the other. */
    private function cacheKey(Request $request, string $challenge): string
    {
        return 'worker-access-device:'.$this->tenant->id().':'.hash('sha256', $request->session()->getId()).':'.hash('sha256', $challenge);
    }

    private function decode(string $base64Url): ?string
    {
        try {
            return Base64UrlSafe::decodeNoPadding($base64Url);
        } catch (Throwable) {
            return null;
        }
    }
}
