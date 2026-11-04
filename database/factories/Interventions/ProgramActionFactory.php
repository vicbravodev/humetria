<?php

declare(strict_types=1);

namespace Database\Factories\Interventions;

use App\Models\User;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\Professional;
use App\Modules\Interventions\Models\ProgramAction;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A planned action of the first level for the whole center: a review of the prevention policy.
 *
 * @extends Factory<ProgramAction>
 */
final class ProgramActionFactory extends Factory
{
    protected $model = ProgramAction::class;

    /**
     * @return array<model-property<ProgramAction>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'program_id' => fn (array $attributes) => ProgramFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'item_id' => fn (array $attributes) => ProgramItemFactory::new()->state(['tenant_id' => $attributes['tenant_id'], 'program_id' => $attributes['program_id']]),
            'level' => ActionLevel::Organizational,
            'kind' => ActionKind::PolicyReview,
            'title' => 'Revisar la política de prevención',
            'description' => 'Revisar y reforzar la política de prevención de riesgos psicosociales.',
            'subjects' => ActionSubjects::Center,
            'starts_on' => null,
            'due_on' => '2026-12-15',
            'responsible_name' => 'Laura Méndez',
            'responsible_user_id' => null,
            'professional' => null,
            'status' => ActionStatus::Planned,
            'completed_on' => null,
            'cancelled_reason' => null,
            'created_by' => User::factory(),
        ];
    }

    public function inProgress(): self
    {
        return $this->state(['status' => ActionStatus::InProgress]);
    }

    public function done(): self
    {
        return $this->state(['status' => ActionStatus::Done, 'completed_on' => '2026-12-01']);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => ActionStatus::Cancelled, 'cancelled_reason' => 'Ya no aplica.']);
    }

    /** The third level: clinical care given by a psychologist, offered as a service (never to a named person). */
    public function clinical(): self
    {
        return $this->state(['level' => ActionLevel::Individual, 'kind' => ActionKind::ClinicalCare, 'professional' => Professional::Psychologist, 'title' => 'Atención psicológica a solicitud']);
    }
}
