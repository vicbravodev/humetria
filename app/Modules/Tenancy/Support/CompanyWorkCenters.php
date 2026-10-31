<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterProfile;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Pagination\LengthAwarePaginator;

final class CompanyWorkCenters implements WorkCenters
{
    public function all(): array
    {
        return array_values(WorkCenter::query()->orderBy('name')->get(['id', 'name', 'archived_at', 'main_activity'])->map($this->summary(...))->all());
    }

    public function activePage(int $perPage = 25, string $pageName = 'page'): LengthAwarePaginator
    {
        return WorkCenter::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'archived_at', 'main_activity'], $pageName)
            ->withQueryString()
            ->through($this->summary(...));
    }

    public function findActive(string $id): ?WorkCenterSummary
    {
        $workCenter = WorkCenter::query()->whereNull('archived_at')->find($id, ['id', 'name', 'archived_at', 'main_activity']);

        return $workCenter === null ? null : $this->summary($workCenter);
    }

    public function profile(string $id): ?WorkCenterProfile
    {
        $workCenter = WorkCenter::query()->find($id);

        if ($workCenter === null) {
            return null;
        }

        $tenant = Tenant::query()->findOrFail($workCenter->tenant_id, ['id', 'legal_name', 'rfc']);

        return new WorkCenterProfile(
            $workCenter->id,
            $workCenter->name,
            $tenant->legal_name,
            $tenant->rfc,
            $workCenter->address(),
            $workCenter->main_activity,
        );
    }

    public function states(): array
    {
        return collect(MexicanState::cases())->mapWithKeys(fn (MexicanState $state): array => [$state->value => $state->label()])->all();
    }

    private function summary(WorkCenter $workCenter): WorkCenterSummary
    {
        return new WorkCenterSummary($workCenter->id, $workCenter->name, $workCenter->archived_at !== null, $workCenter->main_activity);
    }
}
