<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Assistant\Support\UntrustedText;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * How the company's campaigns are going: who was given each guide and how many answered. Counts only, never a name.
 */
final readonly class GetCampaignProgress implements Tool
{
    private const int MAX_ROWS = 10;

    public function __construct(
        private AssistantScope $scope,
        private CampaignRecords $campaigns,
        private WorkCenters $workCenters,
        private Instruments $instruments,
        private PanelLinks $links,
        private ScopeGuard $guard,
    ) {}

    public function description(): string
    {
        return 'Avance de las campañas de la empresa: a cuántas personas se les dio cada guía y cuántas ya contestaron, con la fecha de cierre. '
            .'Sin campaign_id lista las campañas en curso, los borradores y las cerradas en los últimos 30 días. Nunca da nombres de personas.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);
        $id = $request['campaign_id'] ?? null;

        if ($id === null) {
            return $this->all()->toJson();
        }

        $campaign = is_string($id) && Str::isUuid($id) ? $this->campaigns->find($id) : null;

        // A campaign of another company, one that does not exist and a value that is no id all answer the same.
        return ($campaign === null
            ? new ToolCard('progress', 'Avance de la campaña', note: 'No encontré esa campaña en tu empresa.')
            : new ToolCard('progress', 'Avance de la campaña', [$this->row($campaign, $this->centerNames())], $this->links->campaign($campaign->id))
        )->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->string()->description('Id de una campaña, solo si la persona habla de una en particular.'),
        ];
    }

    private function all(): ToolCard
    {
        $current = $this->campaigns->current();
        $names = $this->centerNames();
        $rows = array_map(fn (CampaignRecord $campaign): array => $this->row($campaign, $names), array_slice($current, 0, self::MAX_ROWS));
        $truncated = count($current) > self::MAX_ROWS;

        return new ToolCard(
            'progress',
            'Avance de campañas',
            $rows,
            $this->links->campaigns(),
            match (true) {
                $current === [] => 'Todavía no hay campañas en curso. Puedes lanzar una desde Campañas.',
                $truncated => 'Muestro '.self::MAX_ROWS.' de '.count($current).' campañas; el resto está en Campañas.',
                default => null,
            },
            $truncated,
        );
    }

    /**
     * @return array<string, string> the name of each work center, by id
     */
    private function centerNames(): array
    {
        $names = [];

        foreach ($this->workCenters->all() as $center) {
            $names[$center->id] = $center->name;
        }

        return $names;
    }

    /**
     * @param  array<string, string>  $names
     * @return array<string, mixed>
     */
    private function row(CampaignRecord $campaign, array $names): array
    {
        $guides = [];

        foreach ($campaign->instruments as $key) {
            if (($campaign->invited[$key] ?? 0) > 0) {
                $guides[] = ['label' => $this->instruments->definition($key)->shortTitle, 'invited' => $campaign->invited[$key], 'answered' => $campaign->answered[$key] ?? 0];
            }
        }

        return [
            'label' => UntrustedText::line(($names[$campaign->workCenterId] ?? '').' · '.$campaign->kindLabel),
            'status' => match (true) {
                $campaign->closed => 'closed',
                $campaign->launchedOn === null => 'draft',
                default => 'active',
            },
            'closes_on' => $campaign->closesOn,
            'days_left' => $campaign->daysLeft,
            'guides' => $guides,
            'link' => $this->links->campaign($campaign->id),
        ];
    }
}
