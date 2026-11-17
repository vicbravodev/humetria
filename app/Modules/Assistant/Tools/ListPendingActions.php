<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\TellsLiveFacts;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Assistant\Support\UntrustedText;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Interventions\Contracts\ProgramStandings;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What the company still has to do, most urgent first: a first work center, evaluations overdue or never made, campaigns
 * to launch or about to close, Programas de intervención with overdue actions, and the rows of NOM-037 that are missing or partial. Each item says where to do it.
 */
final readonly class ListPendingActions implements TellsLiveFacts, Tool
{
    private const int MAX_ITEMS = 15;

    private const int CLOSING_DAYS = 7;

    public function __construct(
        private AssistantScope $scope,
        private WorkCenters $workCenters,
        private CampaignRecords $campaigns,
        private EvaluationSchedule $schedule,
        private TeleworkStanding $telework,
        private ProgramStandings $programs,
        private CompanyModules $modules,
        private PanelLinks $links,
        private ScopeGuard $guard,
    ) {}

    public function description(): string
    {
        return 'Pendientes de la empresa, del más urgente al menos: evaluaciones NOM-035 vencidas o sin hacer, campañas por lanzar o por cerrar, programas de intervención con acciones vencidas y los puntos de la tabla 10.3 de NOM-037 que faltan, cada uno con su enlace. No recibe argumentos.';
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
            next: $items === [] ? null : ['label' => 'Ir al primer pendiente', 'link' => $items[0]['link']],
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
     * How many things are pending and when the first was due, how the centers with telework stand with their list, and
     * what to invite to on the pages of campaigns, centers and Programas: counts only.
     */
    public function facts(): array
    {
        $this->guard->check($this->scope);
        $names = $this->centerNames();
        $facts = ['pending' => self::pendingLine($this->items())];

        if ($this->modules->enabled($this->scope->tenantId, 'nom035')) {
            $closing = array_find($this->closing(), fn (CampaignRecord $campaign): bool => self::missing($campaign) > 0);
            $programs = count($this->overduePrograms($names));
            $facts += array_filter([
                'invite.campaigns' => $closing === null ? null : "Cierra el {$closing->closesOn} y ".(self::missing($closing) === 1 ? 'falta 1 cuestionario' : 'faltan '.self::missing($closing).' cuestionarios').'. ¿Te cuento cómo va?',
                'invite.interventions' => match (true) {
                    $programs === 0 => null,
                    $programs === 1 => '1 programa de intervención tiene acciones vencidas. ¿Lo repasamos?',
                    default => "{$programs} programas de intervención tienen acciones vencidas. ¿Los repasamos?",
                },
            ]);
        }

        return $this->modules->enabled($this->scope->tenantId, 'nom037') ? $facts + self::teleworkFacts($this->teleworkStanding($names)) : $facts;
    }

    /**
     * @param  list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>  $items
     */
    private static function pendingLine(array $items): string
    {
        $count = count($items);
        $first = $items[0] ?? null;

        if ($first === null) {
            return 'Nada pendiente por ahora';
        }

        $head = $count === 1 ? '1 pendiente' : "{$count} pendientes";
        $verb = $first['kind'] === 'evaluation_overdue' ? 'venció' : 'vence';

        return match (true) {
            $first['due'] === null => $head,
            $count === 1 => "{$head}; {$verb} el {$first['due']}",
            default => "{$head}; el primero {$verb} el {$first['due']}",
        };
    }

    /**
     * @param  array<string, list<TeleworkRow>>  $standing
     * @return array<string, string>
     */
    private static function teleworkFacts(array $standing): array
    {
        if ($standing === []) {
            return ['telework' => 'Nadie teletrabaja todavía en tus centros'];
        }

        $centers = count($standing);
        $lacking = count(array_filter($standing, fn (array $gaps): bool => $gaps !== []));

        return array_filter([
            'telework' => 'Teletrabajo: '.($centers - $lacking)." de {$centers} ".($centers === 1 ? 'centro' : 'centros').' con su lista completa',
            'invite.work_centers' => match ($lacking) {
                0 => null,
                1 => 'A 1 centro le falta algo de su lista de NOM-037. ¿Te explico qué falta?',
                default => "A {$lacking} centros les falta algo de su lista de NOM-037. ¿Te explico qué falta?",
            },
        ]);
    }

    /**
     * @return array<string, string> the name of each work center not archived, by id
     */
    private function centerNames(): array
    {
        $names = [];

        foreach ($this->workCenters->all() as $center) {
            if (! $center->archived) {
                $names[$center->id] = $center->name;
            }
        }

        return $names;
    }

    /**
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function items(): array
    {
        $names = $this->centerNames();

        if ($names === []) {
            return [['priority' => 0, 'kind' => 'register_work_center', 'title' => 'Registrar tu primer centro de trabajo', 'detail' => 'Las normas se aplican por centro de trabajo.', 'due' => null, 'link' => $this->links->workCenters()]];
        }

        $nom035 = $this->modules->enabled($this->scope->tenantId, 'nom035');
        $items = [
            ...($nom035 ? $this->evaluations($names) : []),
            ...($nom035 ? $this->campaignItems($names) : []),
            ...($nom035 ? $this->overduePrograms($names) : []),
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
            } elseif (self::isClosing($campaign)) {
                $items[] = ['priority' => 3, 'kind' => 'campaign_closing', 'title' => "Cierra pronto: {$label}", 'detail' => self::closingDetail($campaign), 'due' => $campaign->closesOn, 'link' => $this->links->campaign($campaign->id)];
            }
        }

        return $items;
    }

    private static function isClosing(CampaignRecord $campaign): bool
    {
        return ! $campaign->closed && $campaign->daysLeft !== null && $campaign->daysLeft <= self::CLOSING_DAYS;
    }

    /**
     * @return list<CampaignRecord> the campaigns running that close within a week, the soonest first
     */
    private function closing(): array
    {
        return array_values(array_filter($this->campaigns->current(), self::isClosing(...)));
    }

    private static function missing(CampaignRecord $campaign): int
    {
        return array_sum($campaign->invited) - array_sum($campaign->answered);
    }

    /**
     * The Programa de intervención of the latest evaluation of each center, when it has actions past their day.
     *
     * @param  array<string, string>  $names
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function overduePrograms(array $names): array
    {
        $latest = $this->campaigns->latestClosedEvaluations(array_keys($names));
        $standings = $this->programs->forEvaluations(array_values(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $latest)));
        $items = [];

        foreach ($latest as $centerId => $campaign) {
            if (($standings[$campaign->id] ?? null) === 'overdue') {
                $items[] = ['priority' => 3, 'kind' => 'program_overdue', 'title' => 'Programa de intervención con acciones vencidas · '.UntrustedText::line($names[$centerId] ?? ''), 'detail' => null, 'due' => null, 'link' => $this->links->program($campaign->id)];
            }
        }

        return $items;
    }

    private static function closingDetail(CampaignRecord $campaign): string
    {
        $missing = self::missing($campaign);

        return "Cierra el {$campaign->closesOn}.".($missing > 0 ? " Faltan {$missing} cuestionarios por contestar." : '');
    }

    /**
     * @param  array<string, string>  $names
     * @return list<array{priority: int, kind: string, title: string, detail: string|null, due: string|null, link: string}>
     */
    private function teleworkGaps(array $names): array
    {
        $items = [];

        foreach ($this->teleworkStanding($names) as $centerId => $gaps) {
            foreach ($gaps as $row) {
                $items[] = ['priority' => 5, 'kind' => 'telework_gap', 'title' => "NOM-037 {$row->numeral} {$row->label} · ".UntrustedText::line($names[$centerId] ?? ''), 'detail' => $row->detail, 'due' => null, 'link' => $this->links->teleworkCenter($centerId)];
            }
        }

        return $items;
    }

    /**
     * The rows missing or partial of each center where someone teleworks or is about to (row 5.1 applies); a center
     * without telework is left out, one up to date has none.
     *
     * @param  array<string, string>  $names
     * @return array<string, list<TeleworkRow>> by work center id
     */
    private function teleworkStanding(array $names): array
    {
        $standing = [];

        foreach ($this->telework->forCenters(array_keys($names)) as $centerId => $rows) {
            if (array_any($rows, fn (TeleworkRow $row): bool => $row->numeral === '5.1' && $row->state !== 'not_applicable')) {
                $standing[$centerId] = array_values(array_filter($rows, fn (TeleworkRow $row): bool => in_array($row->state, ['missing', 'partial'], true)));
            }
        }

        return $standing;
    }
}
