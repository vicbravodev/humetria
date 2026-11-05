<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Telework\Data\UnreviewedAccidents;
use Carbon\CarbonImmutable;

/**
 * What cannot wait (rediseño v2, «Atiende primero» of board V2-Panel and «Antes de seguir» of board V3-Movil): the accident
 * notices of a center and the people of an evaluation who wait for a clinical assessment, counted, with their one action.
 */
final readonly class PanelUrgencies
{
    /**
     * @param  array<string, string>  $names
     * @return array{norm: string, title: string, detail: string, action: string, href: string}
     */
    public function accident(UnreviewedAccidents $accidents, array $names, string $tenantSlug): array
    {
        return [
            'norm' => 'NOM-037',
            'title' => $accidents->personName === null ? "{$accidents->count} personas avisaron un accidente de trabajo" : "{$accidents->personName} avisó un accidente de trabajo",
            'detail' => ($names[$accidents->workCenterId] ?? '').' · espera desde el '.self::day($accidents->since),
            'action' => 'Revisar aviso',
            // A lone notice opens on its own page (board V3-Aviso); several, on the center's list.
            'href' => $accidents->noticeId === null
                ? route('tenant.telework.notices.index', ['tenant' => $tenantSlug, 'center' => $accidents->workCenterId], false)
                : route('tenant.telework.notices.show', ['tenant' => $tenantSlug, 'notice' => $accidents->noticeId], false),
        ];
    }

    /**
     * @param  array<string, int>  $toChannel
     * @param  list<CampaignRecord>  $campaigns
     * @param  array<string, string>  $names
     * @return list<array{norm: string, title: string, detail: string, action: string, href: string}>
     */
    public function screenings(array $toChannel, array $campaigns, array $names, string $tenantSlug): array
    {
        $listed = [];

        foreach ($campaigns as $campaign) {
            $people = $toChannel[$campaign->id] ?? 0;

            if ($people > 0 && ! isset($listed[$campaign->id])) {
                $listed[$campaign->id] = [
                    'norm' => 'NOM-035',
                    'title' => $people === 1 ? '1 persona espera valoración clínica' : "{$people} personas esperan valoración clínica",
                    'detail' => ($names[$campaign->workCenterId] ?? '').' · Guía I · '.($people === 1 ? 'canalízala' : 'canalízalas').' a atención',
                    'action' => 'Canalizar',
                    'href' => route('tenant.campaigns.screenings', ['tenant' => $tenantSlug, 'campaign' => $campaign->id], false),
                ];
            }
        }

        return array_values($listed);
    }

    /** A Y-m-d day as the design system writes it: "27 feb 2026". */
    private static function day(string $date): string
    {
        return str_replace('.', '', CarbonImmutable::parse($date)->isoFormat('D MMM YYYY'));
    }
}
