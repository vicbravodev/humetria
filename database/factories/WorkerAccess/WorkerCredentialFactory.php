<?php

declare(strict_types=1);

namespace Database\Factories\WorkerAccess;

use App\Modules\WorkerAccess\Models\WorkerCredential;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A key in use, of a key nobody holds.
 *
 * @extends Factory<WorkerCredential>
 */
final class WorkerCredentialFactory extends Factory
{
    protected $model = WorkerCredential::class;

    /**
     * @return array<model-property<WorkerCredential>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'credential_id' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            'public_key' => rtrim(strtr(base64_encode(random_bytes(77)), '+/', '-_'), '='),
            'sign_count' => 0,
            'backup_eligible' => false,
            'backup_state' => false,
            'label' => 'iPhone',
            'registered_via' => 'issued_link',
            'registered_at' => now(),
        ];
    }
}
