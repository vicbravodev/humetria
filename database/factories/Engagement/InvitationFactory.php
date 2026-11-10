<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A person of the survey's company, invited to it and not yet answered, with no group in the snapshot.
 *
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<model-property<Invitation>, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => SurveyFactory::new()->active(),
            'tenant_id' => fn (array $attributes) => $this->survey($attributes)->tenant_id,
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'invited_at' => now(),
            'invitation_status' => InvitationStatus::Sent,
        ];
    }

    public function answered(): self
    {
        return $this->state(['answered' => true]);
    }

    public function cancelled(): self
    {
        return $this->state(['cancelled' => true]);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function survey(array $attributes): Survey
    {
        $id = $attributes['survey_id'];

        return Survey::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A survey id is needed.'));
    }
}
