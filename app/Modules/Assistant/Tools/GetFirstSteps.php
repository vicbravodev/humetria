<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Assistant\Support\ScopeGuard;
use App\Modules\FirstSteps\Contracts\FirstSteps;
use App\Modules\FirstSteps\Data\FirstStep;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The first steps of the company for its owner: which are done, which is next and where to do it. Only titles, state and
 * links: nothing individual. Anyone who is not the owner gets none.
 */
final readonly class GetFirstSteps implements Tool
{
    /** The titles of the panel's steps (lib/first-steps/steps.ts), by step key. */
    private const array TITLES = [
        'work_centers' => 'Revisa tus centros de trabajo',
        'people' => 'Carga a tu gente',
        'team' => 'Invita a alguien de tu equipo',
        'humi' => 'Conoce a Humi',
    ];

    /** The title of the evaluation step, by the tool it points at. */
    private const array EVALUATION_TITLES = [
        'nom035' => 'Lanza tu primera evaluación NOM-035',
        'nom037' => 'Dinos a qué centros les aplica NOM-037',
        'seating' => 'Dinos a qué centros les aplica Ley Silla',
        'attendance' => 'Configura el registro de jornada',
    ];

    public function __construct(
        private AssistantScope $scope,
        private FirstSteps $firstSteps,
        private PanelLinks $links,
        private ScopeGuard $guard,
    ) {}

    public function description(): string
    {
        return 'Los primeros pasos de la empresa para su owner: cuáles ya hizo, cuál sigue y el enlace a su pantalla. No recibe argumentos.';
    }

    public function handle(Request $request): string
    {
        $this->guard->check($this->scope);
        $steps = $this->firstSteps->forUser($this->scope->userId)->steps ?? [];

        return (new ToolCard(
            'list',
            'Primeros pasos',
            array_map(fn (FirstStep $step): array => [
                'kind' => 'first_step',
                'title' => $this->title($step),
                'detail' => $step->done ? 'Hecho' : 'Pendiente',
                'due' => null,
                'link' => $step->href ?? $this->links->dashboard(),
            ], $steps),
            $this->links->dashboard(),
            $steps === [] ? 'No tienes primeros pasos pendientes.' : null,
        ))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private function title(FirstStep $step): string
    {
        return ($step->key === 'evaluation' ? self::EVALUATION_TITLES[$step->tool ?? ''] ?? null : self::TITLES[$step->key] ?? null) ?? $step->key;
    }
}
