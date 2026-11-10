<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Data\CompanyBrief;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class TenantRegistry implements CompanyRegistry
{
    public function find(string $id): ?CompanyBrief
    {
        return $this->many([$id])[$id] ?? null;
    }

    public function many(array $ids): array
    {
        $uuids = array_values(array_filter($ids, Str::isUuid(...)));

        return $uuids === [] ? [] : $this->briefs(Tenant::query()->whereIn('id', $uuids)->get());
    }

    public function search(string $term, int $limit = 20): array
    {
        $tenants = Tenant::query()
            ->matching(['trade_name', 'legal_name', 'rfc'], trim($term))
            ->orderBy('trade_name')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_values($this->briefs($tenants));
    }

    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<string, CompanyBrief> in the order of the tenants.
     */
    private function briefs(Collection $tenants): array
    {
        // One question for every company: the centers each one uses, the archived ones left out. The scope that keeps a
        // company to its own centers is lifted on purpose, to count for all of them.
        $centers = WorkCenter::query()->withoutGlobalScopes()
            ->whereIn('tenant_id', $tenants->map(fn (Tenant $tenant): string => $tenant->id)->all())
            ->whereNull('archived_at')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->toBase()
            ->pluck('total', 'tenant_id');

        $briefs = [];

        foreach ($tenants as $tenant) {
            $status = $tenant->status();
            $count = $centers->get($tenant->id);

            $briefs[$tenant->id] = new CompanyBrief(
                $tenant->id,
                $tenant->trade_name,
                $tenant->legal_name,
                $tenant->rfc,
                $tenant->slug,
                $status->value,
                $status->label(),
                $status->blocksPanel(),
                is_numeric($count) ? (int) $count : 0,
            );
        }

        return $briefs;
    }
}
