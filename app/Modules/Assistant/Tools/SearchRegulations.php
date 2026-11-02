<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Tools;

use App\Modules\Assistant\Data\RegulationChunk;
use App\Modules\Assistant\Data\ToolCard;
use App\Modules\Assistant\Support\RegulationIndex;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * What the norms say and how Humetría applies them, from the criteria documents. The corpus is the same for every
 * company: this tool reads nothing of any of them, so it needs no scope.
 */
final readonly class SearchRegulations implements Tool
{
    private const int LIMIT = 3;

    private const int MAX_TEXT = 1200;

    public function __construct(private RegulationIndex $index) {}

    public function description(): string
    {
        return 'Busca en los criterios con los que Humetría aplica la NOM-035 y la NOM-037 (qué dice la norma, cómo se aplica y en qué se basa). '
            .'Devuelve hasta 3 criterios con su número y su fuente. Úsala para explicar normativa; no para datos de la empresa.';
    }

    public function handle(Request $request): string
    {
        $query = $request['query'] ?? null;

        if (! is_string($query) || trim($query) === '') {
            return (new ToolCard('list', 'Normativa', note: 'Dime qué tema de la norma quieres consultar.'))->toJson();
        }

        $found = $this->index->search($query, self::LIMIT);

        return (new ToolCard(
            'list',
            'Normativa',
            array_map(fn (RegulationChunk $chunk): array => ['norm' => $chunk->norm, 'id' => $chunk->id, 'title' => $chunk->title, 'source' => $chunk->source, 'text' => mb_strimwidth($chunk->text, 0, self::MAX_TEXT, '…')], $found),
            note: $found === [] ? 'No encontré nada en los criterios de Humetría sobre eso. Puede ser un tema que aún no cubro.' : null,
        ))->toJson();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Lo que se quiere saber de la norma, con las palabras del tema.')->required(),
        ];
    }
}
