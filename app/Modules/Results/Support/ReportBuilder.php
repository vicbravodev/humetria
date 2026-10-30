<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * The report of a closed NOM-035 evaluation (num. 7.7): the work center (a), objective (b), activities (c), method (d),
 * results (e), conclusions (f), recommendations (g) and who is responsible (h). The platform writes what it knows;
 * HR fills in the rest (ReportDetails). Its results are the center's and its areas', never its questions'.
 */
final readonly class ReportBuilder
{
    private const array NUMERALS = ['nom035.guide-ii' => 'II', 'nom035.guide-iii' => 'III'];

    public function __construct(private ResultsPages $pages, private WorkCenters $workCenters) {}

    /** The evaluation's guide scored by risk level, when it is closed and has one; else it has no report. */
    public static function guideOf(CampaignRecord $campaign): ?string
    {
        return $campaign->closed ? ResultsPages::riskGuide($campaign) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CampaignRecord $campaign, string $guide): array
    {
        $profile = $this->workCenters->profile($campaign->workCenterId);
        $details = ReportDetails::query()->where('campaign_id', $campaign->id)->first();
        $risk = $this->pages->risk($campaign, $guide, null);

        return [
            'center' => [
                'legalName' => $profile->legalName ?? '',
                'rfc' => $profile->rfc ?? '',
                'name' => $profile->name ?? '',
                'address' => $profile->address ?? '',
                'mainActivity' => $profile->mainActivity ?? '',
            ],
            'guide' => 'Guía '.self::NUMERALS[$guide],
            'period' => self::period($campaign),
            'participation' => ['invited' => $campaign->invited[$guide] ?? 0, 'answered' => $campaign->answered[$guide] ?? 0],
            'method' => $this->method($campaign, $guide),
            'objective' => $details->objective ?? self::objective($guide),
            'activities' => $details->activities ?? $profile->mainActivity ?? '',
            'conclusions' => $details?->conclusions,
            'recommendations' => $details?->recommendations,
            'responsible' => $details === null ? null : ['name' => $details->responsible_name, 'license' => $details->responsible_license],
            'complete' => $details !== null,
            'results' => $risk['results'],
            'areas' => $risk['areas'],
        ];
    }

    /**
     * What HR edits, starting from what the report says.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, string>
     */
    public static function form(array $report): array
    {
        $responsible = is_array($report['responsible']) ? $report['responsible'] : [];

        return [
            'objective' => self::text($report['objective']),
            'activities' => self::text($report['activities']),
            'conclusions' => self::text($report['conclusions']),
            'recommendations' => self::text($report['recommendations']),
            'responsible_name' => self::text($responsible['name'] ?? null),
            'responsible_license' => self::text($responsible['license'] ?? null),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function period(CampaignRecord $campaign): string
    {
        return "{$campaign->launchedOn} al {$campaign->closedOn}";
    }

    private static function objective(string $guide): string
    {
        $scope = $guide === 'nom035.guide-iii' ? 'los factores de riesgo psicosocial y evaluar el entorno organizacional' : 'los factores de riesgo psicosocial';

        return "Identificar y analizar {$scope} del centro de trabajo, conforme a la NOM-035-STPS-2018.";
    }

    private function method(CampaignRecord $campaign, string $guide): string
    {
        $numeral = self::NUMERALS[$guide];
        $workers = $campaign->workers ?? 0;
        $who = $campaign->sample
            ? "una muestra representativa de {$campaign->sampleSize} de {$workers} trabajadores (III.1), seleccionada al azar y en proporción por género"
            : "todos los trabajadores del centro ({$workers})";
        $guideOne = in_array('nom035.guide-i', $campaign->instruments, true) ? ', y la Guía de referencia I a todas las personas' : '';
        $minimum = config()->integer('results.min_group');

        return "Cuestionario de la Guía de referencia {$numeral} de la NOM-035-STPS-2018 (DOF 23/10/2018), aplicado en línea del ".self::period($campaign)." a {$who}{$guideOne}. "
            ."Cada cuestionario se calificó por sí solo, por categoría, dominio y calificación final ({$numeral}.3), sin promedios. "
            ."Los resultados de grupos con menos de {$minimum} cuestionarios no se muestran, para proteger el anonimato de las respuestas.";
    }
}
