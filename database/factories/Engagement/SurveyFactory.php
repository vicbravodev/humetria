<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Models\User;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A climate survey draft with the first-wave questionnaire, by default.
 *
 * @extends Factory<Survey>
 */
final class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    /**
     * @return array<model-property<Survey>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'kind' => SurveyKind::Climate,
            'status' => SurveyStatus::Draft,
            'name' => 'Clima · noviembre 2026',
            'bank_version' => ItemBank::VERSION,
            'items' => Questionnaire::climate(false),
            'created_by' => User::factory(),
        ];
    }

    public function active(?string $closesOn = null): self
    {
        return $this->state([
            'status' => SurveyStatus::Active,
            'launched_at' => now(),
            'closes_on' => $closesOn ?? now()->addDays(14)->toDateString(),
            'invited' => 12,
        ]);
    }

    public function closed(): self
    {
        return $this->active(now()->subDay()->toDateString())->state(['status' => SurveyStatus::Closed, 'closed_at' => now()]);
    }

    public function pulse(): self
    {
        return $this->state(['kind' => SurveyKind::Pulse, 'name' => 'Pulso · noviembre 2026']);
    }
}
