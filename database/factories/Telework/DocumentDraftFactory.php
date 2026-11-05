<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The policy being written, on its first question with nothing answered yet.
 *
 * @extends Factory<DocumentDraft>
 */
final class DocumentDraftFactory extends Factory
{
    protected $model = DocumentDraft::class;

    /**
     * @return array<model-property<DocumentDraft>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'kind' => DocumentKind::Policy,
            'answers' => [],
            'edits' => [],
            'step' => 0,
            'updated_by' => User::factory(),
        ];
    }
}
