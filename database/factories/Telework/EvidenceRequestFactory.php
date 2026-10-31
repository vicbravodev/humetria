<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * An open request for photos.
 *
 * @extends Factory<EvidenceRequest>
 */
final class EvidenceRequestFactory extends Factory
{
    protected $model = EvidenceRequest::class;

    /**
     * @return array<model-property<EvidenceRequest>, mixed>
     */
    public function definition(): array
    {
        return [
            'workplace_id' => WorkplaceFactory::new(),
            'tenant_id' => function (array $attributes) {
                $id = $attributes['workplace_id'];

                return Workplace::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A workplace id is needed.'))->tenant_id;
            },
            'kind' => EvidenceKind::Photos,
            'note' => 'Una foto de la silla y del escritorio.',
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }
}
