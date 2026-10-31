<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A home office in Querétaro.
 *
 * @extends Factory<Workplace>
 */
final class WorkplaceFactory extends Factory
{
    protected $model = Workplace::class;

    /**
     * @return array<model-property<Workplace>, mixed>
     */
    public function definition(): array
    {
        return [
            'teleworker_id' => TeleworkerFactory::new(),
            'tenant_id' => fn (array $attributes) => $this->teleworker($attributes)->tenant_id,
            'street' => 'Calle Hidalgo',
            'exterior_number' => '45',
            'interior_number' => null,
            'neighborhood' => 'Centro',
            'postal_code' => '76000',
            'municipality' => 'Querétaro',
            'state' => 'QUE',
        ];
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function teleworker(array $attributes): Teleworker
    {
        $id = $attributes['teleworker_id'];

        return Teleworker::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A teleworker id is needed.'));
    }
}
