<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\ComplianceReaders;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\Tenancy\Contracts\CompanyModules;
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
        private CompanyModules $modules,
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
            // A norm is a module of the company: one it does not have is not told.
            array_values(array_map(
                fn (ComplianceReader $reader): array => $reader->status()->toArray(),
                array_filter($this->readers->all(), fn (ComplianceReader $reader): bool => ($module = $this->readers->moduleOf($reader)) === null || $this->modules->enabled($this->scope->tenantId, $module)),
            )),
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
