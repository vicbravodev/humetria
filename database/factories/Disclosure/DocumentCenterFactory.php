<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Modules\Disclosure\Models\DocumentCenter;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A published policy that reached a work center, both of the same company.
 *
 * @extends Factory<DocumentCenter>
 */
final class DocumentCenterFactory extends Factory
{
    protected $model = DocumentCenter::class;

    /**
     * @return array<model-property<DocumentCenter>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'document_id' => fn (array $attributes) => DocumentFactory::new()->published()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
        ];
    }
}
