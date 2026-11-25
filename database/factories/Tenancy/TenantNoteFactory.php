<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantNote>
 */
final class TenantNoteFactory extends Factory
{
    protected $model = TenantNote::class;

    /**
     * @return array<model-property<TenantNote>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'author_id' => null,
            'body' => 'Pidió mover la campaña de enero a febrero',
        ];
    }
}
