<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Models\SeatingDocument;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The Comisión's minutes of a tour made last month, covering the registry of the assessments.
 *
 * @extends Factory<SeatingDocument>
 */
final class SeatingDocumentFactory extends Factory
{
    protected $model = SeatingDocument::class;

    /**
     * @return array<model-property<SeatingDocument>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => DocumentKind::CommissionMinutes,
            'title' => 'Acta de recorrido '.fake()->unique()->numerify('####'),
            'document_on' => now()->subMonth()->toDateString(),
            'incisos' => ['B', 'C'],
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }
}
