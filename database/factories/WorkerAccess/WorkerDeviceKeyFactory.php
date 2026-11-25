<?php

declare(strict_types=1);

namespace Database\Factories\WorkerAccess;

use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;

/**
 * A device key in use, with a real P-256 public key (nobody holds the private one: the tests that sign use Tests\Support\SoftDeviceKey).
 *
 * @extends Factory<WorkerDeviceKey>
 */
final class WorkerDeviceKeyFactory extends Factory
{
    protected $model = WorkerDeviceKey::class;

    /**
     * @return array<model-property<WorkerDeviceKey>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'public_key' => self::publicKey(),
            'label' => 'Este teléfono',
        ];
    }

    /** Revoked, for `$reason` (reissued, not_me, replaced or left_company), at `$at` or now. */
    public function revoked(string $reason = 'reissued', ?CarbonImmutable $at = null): static
    {
        return $this->state(['revoked_at' => $at ?? CarbonImmutable::now(), 'revoked_reason' => $reason]);
    }

    /** A P-256 public key as SPKI DER in base64url. */
    private static function publicKey(): string
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) ?: throw new RuntimeException('No EC key.');
        $details = openssl_pkey_get_details($key) ?: throw new RuntimeException('No key details.');
        $pem = is_string($details['key'] ?? null) ? $details['key'] : throw new RuntimeException('No public key.');

        return Base64UrlSafe::encodeUnpadded((string) base64_decode((string) preg_replace('/-----[A-Z ]+-----|\s/', '', $pem), true));
    }
}
