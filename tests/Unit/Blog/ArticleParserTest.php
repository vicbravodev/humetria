<?php

declare(strict_types=1);

use App\Modules\Blog\Exceptions\InvalidArticle;
use App\Modules\Blog\Support\ArticleParser;

mutates(ArticleParser::class);

/** A body of $words words, with the internal link every article must carry. */
function articleBody(int $words = 520, string $extra = ''): string
{
    return "## Qué dice la ley\n\n".trim(str_repeat('palabra ', $words - 7))."\n\n[Agenda una demo](/#agenda-una-demo)\n\n{$extra}";
}

/**
 * @param  array<string, string>  $front  the lines of the front matter that replace the valid ones, by key; '' drops the key
 */
function articleSource(array $front = [], ?string $body = null): string
{
    $lines = [
        'title' => 'title: "Jornada de 40 horas: el calendario completo"',
        'description' => 'description: "'.str_repeat('d', 130).'"',
        'tool' => 'tool: attendance',
        'published_at' => 'published_at: 2026-11-17',
        'updated_at' => 'updated_at: 2026-11-20',
        'reviewed_by' => 'reviewed_by: "Equipo Humetría"',
        'reviewed_at' => 'reviewed_at: 2026-11-18',
        'sources' => "sources:\n  - https://dof.gob.mx/nota_detalle.php?codigo=1",
        ...$front,
    ];

    return "---\n".implode("\n", array_filter($lines, fn (string $line): bool => $line !== ''))."\n---\n".($body ?? articleBody());
}

it('reads a reviewed article', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource());

    expect($article->slug)->toBe('jornada-40-horas')
        ->and($article->title)->toBe('Jornada de 40 horas: el calendario completo')
        ->and($article->publishedAt->toDateString())->toBe('2026-11-17')
        ->and($article->updatedAt->toDateString())->toBe('2026-11-20')
        ->and($article->reviewedBy)->toBe('Equipo Humetría')
        ->and($article->reviewedAt?->toDateString())->toBe('2026-11-18')
        ->and($article->sources)->toBe(['https://dof.gob.mx/nota_detalle.php?codigo=1'])
        ->and($article->html)->toContain('<h2 id="que-dice-la-ley">Qué dice la ley</h2>')
        ->and($article->readingMinutes)->toBe(3);
});

it('reads a quoted date the same as a bare one', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource(['published_at' => 'published_at: "2026-11-17"']));

    expect($article->publishedAt->toDateString())->toBe('2026-11-17');
});

it('takes the update date from the publication date when it is missing', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource(['updated_at' => '']));

    expect($article->updatedAt->toDateString())->toBe('2026-11-17');
});

it('reads a draft, an article nobody has reviewed yet, without error', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource(['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']));

    expect($article->reviewedAt)->toBeNull()
        ->and($article->isPublished(new DateTimeImmutable('2030-01-01')))->toBeFalse();
});

it('is published once reviewed and on or after its publication day', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource());

    expect($article->isPublished(new DateTimeImmutable('2026-11-16 23:59:59')))->toBeFalse()
        ->and($article->isPublished(new DateTimeImmutable('2026-11-17 00:00:00')))->toBeTrue();
});

it('counts the words of the body to give the minutes of reading', function (): void {
    expect(ArticleParser::parse('a-b', articleSource(body: articleBody(500)))->readingMinutes)->toBe(3)
        ->and(ArticleParser::parse('a-b', articleSource(body: articleBody(900)))->readingMinutes)->toBe(5);
});

it('rejects what the rules forbid, and says which', function (string $slug, string $source, string $problem): void {
    try {
        ArticleParser::parse($slug, $source);
    } catch (InvalidArticle $invalid) {
        expect($invalid->problems)->toContain($problem);

        return;
    }

    $this->fail("The article was accepted, expected: {$problem}");
})->with([
    'no front matter' => ['a-b', articleBody(), 'The file has no front matter between --- lines.'],
    'front matter that is not a map' => ['a-b', "---\n- one\n---\n".articleBody(), 'The front matter is not a map of keys.'],
    'slug in capitals' => ['Jornada', articleSource(), 'The slug (the file name) must be lowercase words joined by hyphens.'],
    'slug too long' => [str_repeat('a', 81), articleSource(), 'The slug (the file name) must be lowercase words joined by hyphens.'],
    'no title' => ['a-b', articleSource(['title' => '']), 'title is required.'],
    'title over 60' => ['a-b', articleSource(['title' => 'title: "'.str_repeat('t', 61).'"']), 'title must have at most 60 characters.'],
    'description under 120' => ['a-b', articleSource(['description' => 'description: "'.str_repeat('d', 119).'"']), 'description must have 120 to 160 characters.'],
    'description over 160' => ['a-b', articleSource(['description' => 'description: "'.str_repeat('d', 161).'"']), 'description must have 120 to 160 characters.'],
    'no publication date' => ['a-b', articleSource(['published_at' => '']), 'published_at must be a date like 2026-11-17.'],
    'a date that is not one' => ['a-b', articleSource(['published_at' => 'published_at: ayer']), 'published_at must be a date like 2026-11-17.'],
    'an impossible date' => ['a-b', articleSource(['published_at' => 'published_at: "2026-02-30"']), 'published_at must be a date like 2026-11-17.'],
    'updated before published' => ['a-b', articleSource(['updated_at' => 'updated_at: 2026-11-16']), 'updated_at cannot be before published_at.'],
    'reviewed without reviewer' => ['a-b', articleSource(['reviewed_by' => '']), 'reviewed_by is required when reviewed_at is set.'],
    'no sources' => ['a-b', articleSource(['sources' => 'sources: []']), 'sources needs at least one official source.'],
    'source not https' => ['a-b', articleSource(['sources' => "sources:\n  - http://dof.gob.mx/x"]), 'sources must be https URLs of gob.mx: http://dof.gob.mx/x'],
    'source outside gob.mx' => ['a-b', articleSource(['sources' => "sources:\n  - https://example.com/x"]), 'sources must be https URLs of gob.mx: https://example.com/x'],
    'source that only ends like gob.mx' => ['a-b', articleSource(['sources' => "sources:\n  - https://notgob.mx/x"]), 'sources must be https URLs of gob.mx: https://notgob.mx/x'],
    'a title in the body' => ['a-b', articleSource(body: articleBody(extra: "# Otro título\n")), 'The body must not have a level 1 heading: the title is the h1.'],
    'raw html' => ['a-b', articleSource(body: articleBody(extra: "<script>alert(1)</script>\n")), 'The body must not have raw HTML: write Markdown.'],
    'no internal link' => ['a-b', articleSource(body: str_repeat('palabra ', 520)), 'The body needs a link to a page of Humetría (starting with /).'],
    'too short' => ['a-b', articleSource(body: articleBody(499)), 'The body needs at least 500 words.'],
]);

it('accepts the limits exactly', function (): void {
    $source = articleSource([
        'title' => 'title: "'.str_repeat('t', 60).'"',
        'description' => 'description: "'.str_repeat('d', 120).'"',
        'sources' => "sources:\n  - https://trabajoseguro.stps.gob.mx/x\n  - https://gob.mx/y",
    ], articleBody(500));

    expect(ArticleParser::parse('a', $source)->title)->toHaveLength(60)
        ->and(ArticleParser::parse('a-1-b', articleSource(['description' => 'description: "'.str_repeat('d', 160).'"']))->description)->toHaveLength(160)
        ->and(ArticleParser::parse('a', $source)->sources)->toHaveCount(2);
});

it('reports every problem at once', function (): void {
    try {
        ArticleParser::parse('Mal', articleSource(['title' => '', 'sources' => 'sources: []']));
    } catch (InvalidArticle $invalid) {
        expect($invalid->problems)->toHaveCount(3);

        return;
    }

    $this->fail('The article was accepted.');
});

it('knows the tool an article is about, the family that gives it its color and where its covers live', function (): void {
    $article = ArticleParser::parse('jornada-40-horas', articleSource(['tool' => 'tool: leave']));

    expect($article->tool)->toBe('leave')
        ->and($article->toolName)->toBe('Vacaciones y permisos')
        ->and($article->family)->toBe('operations')
        ->and($article->toolRoute)->toBe('tools.leave')
        ->and($article->cover())->toBe('/blog/jornada-40-horas.webp')
        ->and($article->ogImage())->toBe('/blog/jornada-40-horas-og.jpg');
});

it('puts every tool in the family its page wears', function (string $tool, string $family): void {
    expect(ArticleParser::parse('a', articleSource(['tool' => 'tool: '.$tool]))->family)->toBe($family);
})->with([
    ['nom_035', 'compliance'], ['nom_037', 'compliance'], ['ley_silla', 'compliance'], ['attendance', 'compliance'],
    ['complaints', 'listening'], ['engagement', 'listening'],
    ['leave', 'operations'], ['org_chart', 'operations'], ['compensation', 'operations'],
]);

it('rejects a missing or unknown tool', function (string $line): void {
    expect(fn () => ArticleParser::parse('a', articleSource(['tool' => $line])))
        ->toThrow(fn (InvalidArticle $invalid) => expect(implode(' ', $invalid->problems))->toContain('tool must be one of'));
})->with(['', 'tool: payroll', 'tool: 7']);

it('gives each h2 an id and lists them for the table of contents', function (): void {
    $article = ArticleParser::parse('a', articleSource(body: articleBody(extra: "## ¿Cuánto cuesta? Sí y no\n\ntexto\n\n## ¿Cuánto cuesta? Sí y no\n\ntexto")));

    expect($article->html)->toContain('<h2 id="que-dice-la-ley">Qué dice la ley</h2>')
        ->and($article->headings)->toBe([
            ['id' => 'que-dice-la-ley', 'text' => 'Qué dice la ley'],
            ['id' => 'cuanto-cuesta-si-y-no', 'text' => '¿Cuánto cuesta? Sí y no'],
            ['id' => 'cuanto-cuesta-si-y-no-2', 'text' => '¿Cuánto cuesta? Sí y no'],
        ]);
});

it('splits the body where a diagram goes, so the page can draw it in its place', function (): void {
    $article = ArticleParser::parse('a', articleSource(body: articleBody(extra: "## Pasos\n\n[diagrama: siete-pasos]\n\nDespués del diagrama.\n\n## Otro\n\ntexto")));

    expect($article->body)->toHaveCount(3)
        ->and($article->body[0])->toBe(['type' => 'html', 'html' => $article->body[0]['html'] ?? ''])
        ->and($article->body[0]['html'])->toContain('<h2 id="que-dice-la-ley">')->toContain('<h2 id="pasos">Pasos</h2>')
        ->and($article->body[1])->toBe(['type' => 'diagram', 'name' => 'siete-pasos'])
        ->and($article->body[2]['html'])->toContain('Después del diagrama.')->toContain('<h2 id="otro">')
        ->and($article->diagrams)->toBe(['siete-pasos']);
});

it('keeps the whole body in one piece when it has no diagram', function (): void {
    $article = ArticleParser::parse('a', articleSource());

    expect($article->body)->toHaveCount(1)->and($article->body[0]['type'])->toBe('html')->and($article->diagrams)->toBe([]);
});

it('leaves a marker that is not alone in its paragraph as text, and counts no word of a marker', function (): void {
    $article = ArticleParser::parse('a', articleSource(body: articleBody(extra: 'Mira [diagrama: siete-pasos] aquí.')));

    expect($article->diagrams)->toBe([])->and($article->body[0]['html'])->toContain('[diagrama: siete-pasos]');
});

it('leaves a marker with a name that is not a slug as text', function (): void {
    expect(ArticleParser::parse('a', articleSource(body: articleBody(extra: "[diagrama: Siete Pasos]\n\ntexto")))->diagrams)->toBe([]);
});
