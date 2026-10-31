<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\TeleworkDocument;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Version 1 of a policy that covers no item, with a path that points at no stored file.
 *
 * @extends Factory<TeleworkDocument>
 */
final class TeleworkDocumentFactory extends Factory
{
    protected $model = TeleworkDocument::class;

    /**
     * @return array<model-property<TeleworkDocument>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => DocumentKind::Policy,
            'version' => 1,
            'path' => 'documents/'.Str::uuid().'.pdf',
            'covered_items' => [],
            'uploaded_by' => User::factory(),
        ];
    }
}
