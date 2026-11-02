<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/** Where the company stands with each norm Humi covers, one line per norm. */
final readonly class GetComplianceOverview implements Tool
{
    public function __construct(
        private AssistantScope $scope,
        private ComplianceReaders $readers,
        private PanelLinks $links,
        private ScopeGuard $guard,
    ) {}

    public function description(): string
    {
        return 'Estado de cumplimiento de la empresa por norma o módulo (NOM-035, NOM-037…): al día, en curso, pendiente o no aplica, con las cifras que lo explican y el enlace a la pantalla. No recibe argumentos.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);

        return (new ToolCard(
            'status_list',
            'Estado de cumplimiento',
            array_map(fn (ComplianceReader $reader): array => $reader->status()->toArray(), $this->readers->all()),
            $this->links->dashboard(),
        ))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
