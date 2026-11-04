<?php

declare(strict_types=1);

namespace Database\Factories\Complaints;

use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\IdentityMode;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\RandomCode;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A new anonymous complaint of a company. Give it the channel of that company: `channel_id` must belong to the same one.
 *
 * @extends Factory<Complaint>
 */
final class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    /**
     * @return array<model-property<Complaint>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'channel_id' => fn (array $attributes) => ComplaintChannelFactory::new()->active()->state(['tenant_id' => $attributes['tenant_id']]),
            'reference' => RandomCode::make(8),
            'access_key_hash' => hash('sha256', RandomCode::make(20)),
            'type' => ComplaintType::WorkplaceViolence,
            'category' => null,
            'respondent_kind' => RespondentKind::Coworker,
            'work_center_id' => null,
            'identity_mode' => IdentityMode::Anonymous,
            'status' => ComplaintStatus::New,
            'resolution' => null,
            'on_hold' => false,
            'description' => 'Una compañera de turno me trata mal de forma constante.',
            'respondent_text' => null,
            'occurred_on' => null,
            'received_on' => now()->toDateString(),
            'acknowledged_at' => null,
            'closed_at' => null,
            'closed_on' => null,
            'purged_on' => null,
        ];
    }

    public function inReview(): self
    {
        return $this->state(['status' => ComplaintStatus::InReview, 'acknowledged_at' => now()]);
    }

    public function closed(ComplaintResolution $resolution = ComplaintResolution::MeasuresTaken): self
    {
        return $this->state(['status' => ComplaintStatus::Closed, 'resolution' => $resolution, 'acknowledged_at' => now(), 'closed_at' => now(), 'closed_on' => now()->toDateString()]);
    }
}
