<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Actions\RegisterCredential;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Data\CredentialData;
use App\Modules\WorkerAccess\Exceptions\InvalidCredential;

final readonly class CeremonyKeys implements WorkerKeys
{
    public function __construct(private WebAuthnCeremony $ceremony, private RegisterCredential $register) {}

    public function registrationOptions(string $employeeId, string $name): array
    {
        return $this->ceremony->registrationOptions($employeeId, $name);
    }

    public function verifyRegistration(string $employeeId, array $response): ?CredentialData
    {
        try {
            return $this->ceremony->verifyRegistration($employeeId, $response);
        } catch (InvalidCredential) {
            return null;
        }
    }

    public function save(string $employeeId, CredentialData $key, string $label): ?string
    {
        return $this->register->handle($employeeId, $key, $label)?->id;
    }
}
