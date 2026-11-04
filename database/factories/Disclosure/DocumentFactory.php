<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Models\User;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft of the policy, built from the template of the guide. `published()` makes it a version with its number, who
 * published it and when; `policy()` is the default kind, named for readability.
 *
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<model-property<Document>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'kind' => DocumentKind::Policy,
            'version' => null,
            'status' => DocumentStatus::Draft,
            'content' => array_map(fn (PolicySection $section): array => [
                'key' => $section->key,
                'heading' => $section->heading,
                'text' => $section->text,
                'items' => $section->items,
                'list_style' => $section->listStyle,
            ], resolve(Nom035Policy::class)->template()->sections),
            'template_version' => 'dof-2018-10-23',
            'covers' => null,
            'created_by' => null,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function policy(): self
    {
        return $this->state(['kind' => DocumentKind::Policy]);
    }

    public function draft(): self
    {
        return $this->state(['status' => DocumentStatus::Draft, 'version' => null, 'published_at' => null, 'published_by' => null]);
    }

    /** A version with its number, published today by a new user. */
    public function published(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Published,
            'version' => $attributes['version'] ?? 1,
            'covers' => $attributes['kind'] === DocumentKind::Policy ? ['a', 'b', 'c'] : null,
            'published_at' => now(),
            'published_by' => User::factory(),
        ]);
    }
}
