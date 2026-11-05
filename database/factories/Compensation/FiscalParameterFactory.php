<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Modules\Compensation\Models\FiscalParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalParameter>
 */
final class FiscalParameterFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = FiscalParameter::class;

    /**
     * @return array<model-property<FiscalParameter>, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'test_parameter_'.fake()->unique()->numberBetween(1, 999999),
            'state' => null,
            'valid_from' => '2026-01-01',
            'valid_to' => null,
            'value_int' => 100,
            'scale' => 0,
            'source' => 'Fuente de prueba',
            'consulted_on' => '2026-11-03',
            'note' => null,
        ];
    }
}
