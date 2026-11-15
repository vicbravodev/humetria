<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Assistant\Support\UntrustedText;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What the company still has to do, most urgent first: a first work center, evaluations overdue or never made, campaigns
 * to launch or about to close, and the rows of NOM-037 that are missing or partial. Each item says where to do it.
 */
final readonly class ListPendingActions implements Tool
{
    private const int MAX_ITEMS = 15;

    private const int CLOSING_DAYS = 7;

    public function __construct(
        private AssistantScope $scope,
        private WorkCenters $workCenters,
        private CampaignRecords $campaigns,
        private EvaluationSchedule $schedule,
        private TeleworkStanding $telework,
        private CompanyModules $modules,
        private PanelLinks $links,
        private ScopeGuard $guard,
    ) {}

    public function description(): string
    {
        return 'Pendientes de la empresa, del más urgente al menos: evaluaciones NOM-035 vencidas o sin hacer, campañas por lanzar o por cerrar y los puntos de la tabla 10.3 de NOM-037 que faltan, cada uno con su enlace. No recibe argumentos.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);
        $items = $this->items();
        $shown = array_slice($items, 0, self::MAX_ITEMS);
        $truncated = count($items) > self::MAX_ITEMS;

        return (new ToolCard(
            'list',
            'Pendientes',
            array_map(fn (array $item): array => array_diff_key($item, ['priority' => true]), $shown),
            $this->links->dashboard(),
            match (true) {
                $items === [] => 'No tengo pendientes que mostrarte: todo lo que reviso está al día.',
                $truncated => 'Muestro '.self::MAX_ITEMS.' de '.count($items).' pendientes; el resto está en el panel.',
                default => null,
            },
            $truncated,
        ))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function items(): array
    {
        $centers = array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));

        if ($centers === []) {
            return [['priority' => 0, 'kind' => 'register_work_center', 'title' => 'Registrar tu primer centro de trabajo', 'detail' => 'Las normas se aplican por centro de trabajo.', 'due' => null, 'link' => $this->links->workCenters()]];
        }

        $names = array_column(array_map(fn (WorkCenterSummary $center): array => ['id' => $center->id, 'name' => $center->name], $centers), 'name', 'id');
        $nom035 = $this->modules->enabled($this->scope->tenantId, 'nom035');
        $items = [
            ...($nom035 ? $this->evaluations($names) : []),
            ...($nom035 ? $this->campaignItems($names) : []),
            ...($this->modules->enabled($this->scope->tenantId, 'nom037') ? $this->teleworkGaps($names) : []),
        ];
        // Stable: items of the same urgency stay in the order they were found.
        usort($items, fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);

        return $items;
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function evaluations(array $names): array
    {
        $standings = $this->schedule->forWorkCenters(array_keys($names));
        $items = [];

        foreach ($names as $id => $name) {
            $title = 'Evaluar NOM-035 en '.UntrustedText::line($name);
            $standing = $standings[$id] ?? null;

            if ($standing === null) {
                $items[] = ['priority' => 2, 'kind' => 'evaluation_missing', 'title' => $title, 'detail' => 'Todavía no hay una evaluación de este centro.', 'due' => null, 'link' => $this->links->campaigns()];
            } elseif ($standing->state === 'overdue') {
                $items[] = ['priority' => 1, 'kind' => 'evaluation_overdue', 'title' => $title, 'detail' => "Debió hacerse a más tardar el {$standing->day}.", 'due' => $standing->day, 'link' => $this->links->campaigns()];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function campaignItems(array $names): array
    {
        $items = [];

        foreach ($this->campaigns->current() as $campaign) {
            $label = UntrustedText::line(($names[$campaign->workCenterId] ?? '').' · '.$campaign->kindLabel);

            if (! $campaign->closed && $campaign->launchedOn === null) {
                $items[] = ['priority' => 4, 'kind' => 'launch_campaign', 'title' => "Lanzar la campaña de {$label}", 'detail' => null, 'due' => null, 'link' => $this->links->campaign($campaign->id)];
            } elseif (! $campaign->closed && $campaign->daysLeft !== null && $campaign->daysLeft <= self::CLOSING_DAYS) {
                $items[] = ['priority' => 3, 'kind' => 'campaign_closing', 'title' => "Cierra pronto: {$label}", 'detail' => self::closingDetail($campaign), 'due' => $campaign->closesOn, 'link' => $this->links->campaign($campaign->id)];
            }
        }

        return $items;
    }

    private static function closingDetail(CampaignRecord $campaign): string
    {
        $missing = array_sum($campaign->invited) - array_sum($campaign->answered);

        return "Cierra el {$campaign->closesOn}.".($missing > 0 ? " Faltan {$missing} cuestionarios por contestar." : '');
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function teleworkGaps(array $names): array
    {
        $items = [];

        foreach ($this->telework->forCenters(array_keys($names)) as $centerId => $rows) {
            // Only where someone teleworks or is about to (row 5.1 applies).
            if (! array_any($rows, fn (TeleworkRow $row): bool => $row->numeral === '5.1' && $row->state !== 'not_applicable')) {
                continue;
            }

            foreach ($rows as $row) {
                if (in_array($row->state, ['missing', 'partial'], true)) {
                    $items[] = ['priority' => 5, 'kind' => 'telework_gap', 'title' => "NOM-037 {$row->numeral} {$row->label} · ".UntrustedText::line($names[$centerId] ?? ''), 'detail' => $row->detail, 'due' => null, 'link' => $this->links->teleworkCenter($centerId)];
                }
            }
        }

        return $items;
    }
}
