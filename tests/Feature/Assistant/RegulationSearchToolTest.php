<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\RegulationIndex;
use App\Modules\Assistant\Tools\SearchRegulations;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

mutates(SearchRegulations::class, RegulationIndex::class);

beforeEach(function (): void {
    $this->scope = assistantScopeIn(Tenant::factory()->create());
});

it('indexes every criterion of the norms it covers', function (): void {
    $expected = 0;

    foreach (config()->array('humi.regulations') as $path) {
        $expected += preg_match_all('/^### C-\d+\./m', (string) file_get_contents(base_path((string) $path)));
    }

    expect(resolve(RegulationIndex::class)->all())->toHaveCount($expected)
        ->and($expected)->toBeGreaterThan(40);
});

it('finds the criterion that answers a question, with its norm and its source', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => '¿Cuál es el mínimo de personas por grupo en los resultados?']);

    expect($card['render'])->toBe('list')
        ->and($card['rows'][0])->toMatchArray(['norm' => 'NOM-035', 'id' => 'C-23', 'source' => 'Humetría'])
        ->and($card['rows'][0]['title'])->toBe('Mínimo de personas por grupo en los resultados')
        ->and($card['rows'][0]['text'])->toContain('menos de 5 respuestas');
});

it('ignores case and accents, and reaches the other norm', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'TELETRABAJO más del 40 % de la JORNADA']);

    expect($card['rows'][0])->toMatchArray(['norm' => 'NOM-037', 'id' => 'C-01']);
});

it('gives at most three criteria, each cut to a size the model can read', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'norma centro de trabajo personas']);

    expect(count($card['rows']))->toBeLessThanOrEqual(3)
        ->and(collect($card['rows'])->every(fn (array $row): bool => mb_strlen($row['text']) <= 1201))->toBeTrue();
});

it('says it found nothing instead of inventing an answer', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'receta de mole poblano']);

    expect($card['rows'])->toBe([])
        ->and($card['note'])->toBe('No encontré nada en los criterios de Humetría sobre eso. Puede ser un tema que aún no cubro.');
});

it('asks what to look up when the question is empty or no text', function (mixed $query): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => $query]);

    expect($card['rows'])->toBe([])
        ->and($card['note'])->toBe('Dime qué tema de la norma quieres consultar.');
})->with(['empty' => [''], 'spaces' => ['   '], 'a list' => [['a']], 'null' => [null]]);

it('reads the same for every company: it holds nothing of any of them', function (): void {
    $first = toolCard(SearchRegulations::class, $this->scope, ['query' => 'evaluación cada dos años']);
    $other = toolCard(SearchRegulations::class, assistantScopeIn(Tenant::factory()->create()), ['query' => 'evaluación cada dos años']);

    expect($other)->toBe($first);
});

it('takes only a query from the model', function (): void {
    expect(array_keys(resolve(SearchRegulations::class, ['scope' => $this->scope])->schema(new JsonSchemaTypeFactory)))->toBe(['query']);
});
