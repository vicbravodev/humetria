<?php

declare(strict_types=1);

namespace Database\Factories\Complaints;

use App\Modules\Complaints\Models\ComplaintEntry;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An internal note of a handler. Give it the complaint of the same company and a position.
 *
 * @extends Factory<ComplaintEntry>
 */
final class ComplaintEntryFactory extends Factory
{
    protected $model = ComplaintEntry::class;

    /**
     * @return array<model-property<ComplaintEntry>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'complaint_id' => fn (array $attributes) => ComplaintFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'position' => 1,
            'kind' => 'internal_note',
            'author_id' => null,
            'body' => 'Una nota del caso.',
            'meta' => null,
            'occurred_at' => now(),
            'occurred_on' => null,
        ];
    }

    /** A message of the person: a day and never an hour, and nobody as its author. */
    public function fromReporter(string $day): self
    {
        return $this->state(['kind' => 'reporter_message', 'author_id' => null, 'occurred_at' => null, 'occurred_on' => $day]);
    }
}
