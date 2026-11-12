<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Support;

use App\Modules\FirstSteps\Actions\BuildFirstSteps;
use App\Modules\FirstSteps\Actions\CompleteFirstSteps;
use App\Modules\FirstSteps\Contracts\FirstSteps;
use App\Modules\FirstSteps\Data\FirstStepsData;
use App\Modules\FirstSteps\Models\FirstStepsState;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;

final readonly class CompanyFirstSteps implements FirstSteps
{
    public function __construct(
        private CurrentTenant $tenant,
        private Companies $companies,
        private Members $members,
        private BuildFirstSteps $build,
        private CompleteFirstSteps $complete,
    ) {}

    public function forUser(int $userId, bool $impersonated = false): ?FirstStepsData
    {
        if (config('first_steps.enabled') !== true) {
            return null;
        }

        $state = FirstStepsState::query()->where('user_id', $userId)->first();
        if ($state?->completed_at !== null) {
            return null;
        }

        if ($this->members->find($userId)?->role->value !== 'owner') {
            return null;
        }

        $company = $this->companies->current();
        if ($company->blocked || $company->readOnly || $company->isUnion()) {
            return null;
        }

        $welcomed = $state?->welcomed_at !== null;
        $hidden = $state?->hidden_at !== null;
        $steps = $this->build->handle($this->tenant->id(), $userId);

        if (! $steps->allDone()) {
            return $steps->withState($welcomed, $hidden);
        }

        if (! $impersonated) {
            $this->complete->handle($this->tenant->id(), $userId);
        }

        // Only who was following the steps sees them finished, once.
        return $welcomed && ! $hidden ? $steps->withState($welcomed, $hidden) : null;
    }
}
