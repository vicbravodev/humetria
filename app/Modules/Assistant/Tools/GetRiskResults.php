<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Actions\RecordAudit;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Assistant\Support\UntrustedText;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaResult;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The risk results of a closed NOM-035 evaluation as people in each level (never an average), and only for a group of
 * at least the minimum. Below it the card has no figure, not even how many answered.
 */
final readonly class GetRiskResults implements Tool
{
    private const int MAX_ROWS = 40;

    public function __construct(
        private AssistantScope $scope,
        private CampaignRecords $campaigns,
        private RiskResults $results,
        private PanelLinks $links,
        private ScopeGuard $guard,
        private RecordAudit $audit,
    ) {}

    public function description(): string
    {
        return 'Resultados de riesgo psicosocial (NOM-035) de una evaluación cerrada: cuántas personas quedan en cada nivel de riesgo, '
            .'en la calificación final, por categoría y por dominio, y por área. Solo existen con la evaluación cerrada y con el mínimo de personas por grupo. '
            .'Nunca hay respuestas individuales. Requiere campaign_id.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);
        $id = $request['campaign_id'] ?? null;
        $areaId = $request['area_id'] ?? null;
        $campaign = is_string($id) && Str::isUuid($id) ? $this->campaigns->find($id) : null;

        // A campaign of another company, one that does not exist and a value that is no id all answer the same.
        if ($campaign === null) {
            return (new ToolCard('risk_levels', 'Resultados por nivel de riesgo', note: 'No encontré esa campaña en tu empresa.'))->toJson();
        }

        $risk = $this->results->forCampaign($campaign, is_string($areaId) ? $areaId : null);
        $minimum = config()->integer('results.min_group');
        $link = $this->links->results($campaign->id);

        if ($risk->state !== RiskBreakdown::AVAILABLE) {
            return (new ToolCard('risk_levels', 'Resultados por nivel de riesgo', link: $link, note: self::unavailable($risk->state), minGroup: $minimum))->toJson();
        }

        $this->recordView($campaign->id, $risk->areaLabel !== null);
        $rows = self::rows($risk);

        return (new ToolCard(
            'risk_levels',
            $risk->areaLabel === null ? 'Resultados por nivel de riesgo' : 'Resultados de '.UntrustedText::line($risk->areaLabel),
            array_slice($rows, 0, self::MAX_ROWS),
            $link,
            self::note($risk, is_string($areaId)),
            count($rows) > self::MAX_ROWS,
            $minimum,
        ))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->string()->description('Id de la campaña cuyos resultados se piden.')->required(),
            'area_id' => $schema->string()->description('Id de un área que ya apareció en los resultados, solo si la persona pide ver una en particular.'),
        ];
    }

    private static function unavailable(string $state): string
    {
        return match ($state) {
            RiskBreakdown::OPEN => 'La evaluación sigue abierta: los resultados aparecen al cerrarla.',
            RiskBreakdown::NOT_SCORED => 'Esta campaña solo aplica la Guía I y no tiene resultados por nivel de riesgo.',
            default => 'Todavía no hay suficientes respuestas en este grupo para mostrar resultados.',
        };
    }

    private static function note(RiskBreakdown $risk, bool $areaAsked): ?string
    {
        $notes = [];

        if ($areaAsked && $risk->areaLabel === null) {
            $notes[] = 'No puedo mostrar esa área por separado; estos resultados son del centro completo.';
        }

        if ($risk->hiddenAreas > 0) {
            $notes[] = $risk->hiddenAreas === 1
                ? '1 área no se muestra por separado porque tiene pocas respuestas.'
                : "{$risk->hiddenAreas} áreas no se muestran por separado porque tienen pocas respuestas.";
        }

        return $notes === [] ? null : implode(' ', $notes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(RiskBreakdown $risk): array
    {
        return [
            ['kind' => 'final', 'label' => 'Calificación final', 'answered' => $risk->answered, 'levels' => self::levels($risk->final)],
            ...array_map(fn (RiskGroup $group): array => self::group('category', $group), $risk->categories),
            ...array_map(fn (RiskGroup $group): array => self::group('domain', $group), $risk->domains),
            ...array_map(fn (AreaResult $area): array => ['kind' => 'area', 'id' => $area->id, 'label' => UntrustedText::line($area->name), 'answered' => $area->answered, 'levels' => self::levels($area->final)], $risk->areas),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function group(string $kind, RiskGroup $group): array
    {
        return ['kind' => $kind, 'label' => $group->label, 'levels' => self::levels($group->levels)];
    }

    /**
     * @param  list<int>  $counts  from Nulo to Muy alto
     * @return array<string, int> people in each level, by its name
     */
    private static function levels(array $counts): array
    {
        return array_combine(array_map(fn (RiskLevel $level): string => $level->label(), RiskLevel::cases()), $counts);
    }

    private function recordView(string $campaignId, bool $byArea): void
    {
        Log::info('assistant.results_viewed', ['tenant_id' => $this->scope->tenantId, 'user_id' => $this->scope->userId, 'campaign_id' => $campaignId]);
        $this->audit->handle('assistant.results_viewed', null, ['campaign_id' => $campaignId, 'area' => $byArea]);
    }
}
