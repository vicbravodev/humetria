<?php

declare(strict_types=1);

namespace Database\Factories\Notifications;

use App\Modules\Notifications\Models\PushSubscription;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The subscription of a phone nobody holds (Chrome's push service by default). The keys are a real P-256 pair and a 16-byte secret,
 * like a browser's, because the notice is encrypted to them; `keyPair()` gives the private half to a test that wants to read it.
 *
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * What a browser holds for one subscription: a P-256 public key (65 bytes, base64url), its private scalar (32 bytes, base64url)
     * and the secret `auth` (16 bytes, base64url).
     *
     * @return array{public: string, private: string, auth: string}
     */
    public static function keyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = $key === false ? [] : (openssl_pkey_get_details($key) ?: []);
        $part = static fn (string $name): string => str_pad(Arr::string($details, "ec.$name"), 32, "\0", STR_PAD_LEFT);
        $url = static fn (string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        return ['public' => $url("\x04".$part('x').$part('y')), 'private' => $url($part('d')), 'auth' => $url(random_bytes(16))];
    }

    /**
     * @return array<model-property<PushSubscription>, mixed>
     */
    public function definition(): array
    {
        $keys = self::keyPair();

        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::uuid(),
            'p256dh' => $keys['public'],
            'auth' => $keys['auth'],
        ];
    }

    /** A phone on Firefox's push service. */
    public function mozilla(): static
    {
        return $this->state(['endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/'.Str::uuid()]);
    }

    /** A phone on Apple's push service (Safari, the app on the home screen). */
    public function apple(): static
    {
        return $this->state(['endpoint' => 'https://web.push.apple.com/'.Str::uuid()]);
    }

    /** One whose last notice arrived. */
    public function succeeded(): static
    {
        return $this->state(['last_success_at' => now()]);
    }
}
