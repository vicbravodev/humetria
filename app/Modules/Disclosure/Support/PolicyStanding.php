<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;

/**
 * Which version of the policy is in force in each work center (criterio C-50): the highest one that reached it. A center no
 * version reached has none, however many versions the company has. Two reads, whatever the number of centers.
 */
final readonly class PolicyStanding
{
    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, PolicyFact> by work center id; a center without policy is left out.
     */
    public function inForce(array $workCenterIds): array
    {
        $versions = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->orderBy('version')->get(['id', 'version', 'covers', 'published_at']);
        $reached = [];

        foreach (DocumentCenter::query()->whereIn('document_id', $versions->pluck('id'))->whereIn('work_center_id', $workCenterIds)->get(['document_id', 'work_center_id']) as $reach) {
            $reached[$reach->document_id][] = $reach->work_center_id;
        }

        $inForce = [];

        // From the oldest to the newest, so the highest version that reached a center is the one left.
        foreach ($versions as $version) {
            foreach ($reached[$version->id] ?? [] as $center) {
                $inForce[$center] = new PolicyFact($version->id, $version->version ?? 0, $version->covers ?? [], $version->published_at ?? now()->toImmutable());
            }
        }

        return $inForce;
    }
}
