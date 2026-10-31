<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * An authorization asked of the person and not yet given.
 *
 * @extends Factory<VisitAuthorization>
 */
final class VisitAuthorizationFactory extends Factory
{
    protected $model = VisitAuthorization::class;

    /**
     * @return array<model-property<VisitAuthorization>, mixed>
     */
    public function definition(): array
    {
        return [
            'workplace_id' => WorkplaceFactory::new(),
            'tenant_id' => function (array $attributes) {
                $id = $attributes['workplace_id'];

                return Workplace::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A workplace id is needed.'))->tenant_id;
            },
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }

    public function confirmed(): self
    {
        return $this->state(['confirmed_at' => now(), 'confirmed_ip' => '203.0.113.7']);
    }
}
