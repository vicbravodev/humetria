<?php

declare(strict_types=1);

use App\Modules\Assistant\Data\RegulationChunk;
use App\Modules\Assistant\Support\RegulationIndex;
use App\Modules\Assistant\Tools\SearchRegulations;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

mutates(SearchRegulations::class, RegulationIndex::class);

beforeEach(function (): void {
    $this->scope = assistantScopeIn(Tenant::factory()->create());
});

it('indexes every criterion of the norms written as numbered criteria', function (): void {
    $expected = 0;

    foreach (config()->array('humi.regulations') as $path) {
        $expected += preg_match_all('/^(### |\*\*)C-\d+\./m', (string) file_get_contents(base_path((string) $path)));
    }

    $criteria = array_filter(resolve(RegulationIndex::class)->all(), fn (RegulationChunk $chunk): bool => str_starts_with($chunk->id, 'C-'));

    expect($criteria)->toHaveCount($expected)
        ->and($expected)->toBeGreaterThan(60);
});

it('reads every criteria document of docs/normas, so a new norm reaches Humi with its criteria', function (): void {
    $indexed = config()->array('humi.regulations');
    $norms = array_count_values(array_map(fn (RegulationChunk $chunk): string => $chunk->norm, resolve(RegulationIndex::class)->all()));

    foreach (glob(base_path('docs/normas/*/criterios.md')) ?: [] as $document) {
        $path = str_replace(base_path().'/', '', $document);

        expect($indexed)->toContain($path);
        expect($norms[array_search($path, $indexed, true)] ?? 0)->toBeGreaterThan(3, "{$path} gives Humi almost nothing to read");
    }
});

it('reads the Ley Silla criteria, written as bold paragraphs', function (): void {
    $chunk = resolve(RegulationIndex::class)->search('asientos suficientes con respaldo', 1)[0];

    expect($chunk->norm)->toBe('Ley Silla')
        ->and($chunk->id)->toBe('C-01')
        ->and($chunk->title)->toBe('Asientos suficientes, a disposición de todas las personas')
        ->and($chunk->text)->toContain('asientos o sillas con respaldo');
});

it('reads the working time criteria by their numbered points, with their origin', function (): void {
    $chunk = resolve(RegulationIndex::class)->search('horas extra trabajo extraordinario', 1)[0];

    expect($chunk->norm)->toBe('Jornada')
        ->and($chunk->id)->toBe('2.3')
        ->and($chunk->title)->toBe('Horas extra')
        ->and($chunk->source)->toBe('Norma')
        ->and($chunk->text)->toContain('doce horas en una semana');
});

it('reads a sub-point of the working time criteria, as the offline mark', function (): void {
    $chunk = resolve(RegulationIndex::class)->search('marca sin conexión hora declarada no antes de', 1)[0];

    expect($chunk->norm)->toBe('Jornada')
        ->and($chunk->id)->toBe('3.3.1')
        ->and($chunk->title)->toBe('Marca sin conexión')
        ->and($chunk->source)->toBe('Humetría')
        ->and($chunk->text)->toContain('no probada');
});

it('reads the compensation criteria by their numbered sections', function (): void {
    $chunk = resolve(RegulationIndex::class)->search('cesantía y vejez cuota patronal', 1)[0];

    expect($chunk->norm)->toBe('Compensación')
        ->and($chunk->id)->toBe('2.1')
        ->and($chunk->title)->toBe('Cesantía y vejez, cuota patronal (% del SBC)')
        ->and($chunk->text)->toContain('3.150');
});

it('finds the criterion that answers a question, with its norm and its source', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => '¿Cuál es el mínimo de personas por grupo en los resultados?']);

    expect($card['render'])->toBe('list')
        ->and($card['rows'][0])->toMatchArray(['norm' => 'NOM-035', 'id' => 'C-23', 'source' => 'Humetría'])
        ->and($card['rows'][0]['title'])->toBe('Mínimo de personas por grupo en los resultados')
        ->and($card['rows'][0]['text'])->toContain('menos respuestas que el mínimo de la')->toContain('Por omisión es 5');
});

it('ignores case and accents, and reaches the other norm', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'TELETRABAJO más del 40 % de la JORNADA']);

    expect($card['rows'][0])->toMatchArray(['norm' => 'NOM-037', 'id' => 'C-01']);
});

it('indexes the vacation criteria for Humi', function (): void {
    $chunk = resolve(RegulationIndex::class)->search('prima vacacional', 1)[0];

    expect($chunk->norm)->toBe('Vacaciones')
        ->and($chunk->id)->toBe('C-08')
        ->and($chunk->source)->toBe('Ley + Nuestro');
});

it('gives at most three criteria, each cut to a size the model can read', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'norma centro de trabajo personas']);

    expect(count($card['rows']))->toBeLessThanOrEqual(3)
        ->and(collect($card['rows'])->every(fn (array $row): bool => mb_strlen($row['text']) <= 1201))->toBeTrue();
});

it('gives three criteria when more match, and cuts a long one to 1200 characters with an ellipsis', function (): void {
    $card = toolCard(SearchRegulations::class, $this->scope, ['query' => 'Impuesto sobre nómina (ISN) por estado']);
    $long = collect($card['rows'])->firstWhere('title', 'Impuesto sobre nómina (ISN) por estado, 2026');

    expect($card['rows'])->toHaveCount(3)
        ->and(mb_strlen($long['text']))->toBe(1200)
        ->and($long['text'])->toEndWith('…');
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

it('names each criterion it found as a source, with its norm and number, and none when it found nothing', function (): void {
    $found = toolCard(SearchRegulations::class, $this->scope, ['query' => '¿Cuál es el mínimo de personas por grupo en los resultados?']);
    $nothing = toolCard(SearchRegulations::class, $this->scope, ['query' => 'receta de mole poblano']);

    expect($found['sources'])->toHaveCount(count($found['rows']))
        ->and($found['sources'][0])->toBe('NOM-035 · C-23. Mínimo de personas por grupo en los resultados')
        ->and($found['next'])->toBeNull()
        ->and($nothing['sources'])->toBe([]);
});

it('takes only a query from the model', function (): void {
    expect(array_keys(resolve(SearchRegulations::class, ['scope' => $this->scope])->schema(new JsonSchemaTypeFactory)))->toBe(['query']);
});

it('tells the model every norm it can look up, so it reaches for it on any of them', function (): void {
    $description = (string) resolve(SearchRegulations::class, ['scope' => $this->scope])->description();

    foreach (array_keys(config()->array('humi.regulations')) as $norm) {
        expect($description)->toContain((string) $norm);
    }
});

it('skips an entry of the list of norms that names no document', function (): void {
    config()->set('humi.regulations', ['Rota' => ['no es una ruta'], 'Prueba' => 'tests/Fixtures/regulations/weights.md']);

    expect(array_map(fn (RegulationChunk $chunk): string => $chunk->id, resolve(RegulationIndex::class)->all()))
        ->toBe(['C-01', 'C-02', 'C-03', 'C-04', 'C-05', 'C-06', 'C-07']);
});

it('weighs a word in a title as six in the text, counts a word at most five times in the text, and reads words of four letters', function (string $question, string $first): void {
    config()->set('humi.regulations', ['Prueba' => 'tests/Fixtures/regulations/weights.md']);

    expect(resolve(RegulationIndex::class)->search($question, 1)[0]->id)->toBe($first);
})->with([
    'a title beats six times in the text' => ['alfa', 'C-02'],
    'seven in the text beat a title' => ['beta gama', 'C-04'],
    'five times in the text beat four' => ['delta', 'C-06'],
]);

it('finds nothing for a question of only short words or words that say nothing', function (string $question): void {
    expect(resolve(RegulationIndex::class)->search($question, 3))->toBe([]);
})->with(['de la NOM', 'cual como para']);

it('leaves out the words of a question that say nothing about the topic', function (string $word): void {
    config()->set('humi.regulations', ['Prueba' => 'tests/Fixtures/regulations/weights.md']);

    expect(resolve(RegulationIndex::class)->search($word, 3))->toBe([]);
})->with(['cual', 'cuales', 'como', 'para', 'esta', 'este', 'sobre', 'entre', 'donde', 'cuando', 'tiene', 'tienen', 'puedo', 'debo']);
