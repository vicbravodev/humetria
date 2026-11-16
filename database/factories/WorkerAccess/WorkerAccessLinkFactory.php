<?php

declare(strict_types=1);

namespace Database\Factories\WorkerAccess;

use App\Models\User;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A link RH issued that still opens; the token is `token-{id}` unless one is given (only its hash is kept).
 *
 * @extends Factory<WorkerAccessLink>
 */
final class WorkerAccessLinkFactory extends Factory
{
    protected $model = WorkerAccessLink::class;

    /**
     * @return array<model-property<WorkerAccessLink>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'token_hash' => WorkerAccessLink::hashToken(Str::random(48)),
            'kind' => LinkKind::Issued,
            'issued_by' => User::factory(),
            'expires_at' => now()->addHours(48),
        ];
    }

    public function withToken(string $token): self
    {
        return $this->state(['token_hash' => WorkerAccessLink::hashToken($token)]);
    }

    public function requested(): self
    {
        return $this->state(['kind' => LinkKind::Requested, 'issued_by' => null, 'expires_at' => now()->addMinutes(30)]);
    }

    public function used(): self
    {
        return $this->state(['used_at' => now()]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subSecond()]);
    }

    public function revoked(): self
    {
        return $this->state(['revoked_at' => now()]);
    }
}
