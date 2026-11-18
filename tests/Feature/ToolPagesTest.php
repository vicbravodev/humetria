<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Http\IndexablePages;
use App\Http\PageSeo;
use App\Http\SsrPages;
use App\Http\ToolPages;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ToolPages::class);

/** @return array<string, array{string, string}> key => [key, slug] */
function toolPageCases(): array
{
    $cases = [];

    foreach (ToolPages::SLUGS as $key => $slug) {
        $cases[$key] = [$key, $slug];
    }

    return $cases;
}

/** @return array<string, mixed> */
function toolJsonLd(string $key): array
{
    return json_decode(PageSeo::tool($key)['jsonLd'], true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $graph
 * @return array<string, mixed>
 */
function nodeOfType(array $graph, string $type): array
{
    $nodes = array_values(array_filter($graph['@graph'], fn (array $node): bool => $node['@type'] === $type));
    expect($nodes)->toHaveCount(1);

    return $nodes[0];
}

it('has a page for each of the nine tools the demo form offers, and nothing else', function (): void {
    $keys = array_keys(ToolPages::SLUGS);
    $tools = array_map(fn (Tool $tool): string => $tool->value, Tool::cases());
    sort($keys);
    sort($tools);

    expect($keys)->toBe($tools);
});

it('shows each tool at its own address, with its seo and its content', function (string $key, string $slug): void {
    config(['app.url' => 'https://humetria.example']);

    $this->get("/{$slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tool')
            ->where('seo', PageSeo::tool($key))
            ->where('seo.canonical', "https://humetria.example/{$slug}")
            ->where('tool.key', $key)
            ->where('tool.h1', ToolPages::page($key)['h1'])
            ->where('tool.faqs', ToolPages::page($key)['faqs'])
            ->etc());

    expect(route(ToolPages::route($key), [], false))->toBe("/{$slug}");
})->with(toolPageCases());

it('gives each page a title of at most 60 characters and a description of at most 155, none repeated', function (): void {
    $titles = [];
    $descriptions = [];

    foreach (array_keys(ToolPages::SLUGS) as $key) {
        $seo = PageSeo::tool($key);
        expect(mb_strlen($seo['title']))->toBeLessThanOrEqual(60)
            ->and(mb_strlen($seo['description']))->toBeLessThanOrEqual(155)->toBeGreaterThan(70);
        $titles[] = $seo['title'];
        $descriptions[] = $seo['description'];
    }

    $titles[] = PageSeo::for('home')['title'];
    $titles[] = PageSeo::guide()['title'];

    expect(array_unique($titles))->toHaveCount(count($titles))
        ->and(array_unique($descriptions))->toHaveCount(count($descriptions));
});

it('describes each tool with three steps, three to five blocks, a before and after and four to six questions, and promises nothing it cannot', function (string $key): void {
    $page = ToolPages::page($key);
    $text = mb_strtolower(json_encode($page, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    expect($page['steps'])->toHaveCount(3)
        ->and(count($page['features']))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5)
        ->and(count($page['beforeAfter']))->toBeGreaterThanOrEqual(3)
        ->and(count($page['audience']))->toBe(3)
        ->and(count($page['faqs']))->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(6)
        ->and($text)->not->toContain('garantiza')->not->toContain('el mejor')->not->toContain('la mejor')
        ->not->toContain('100 %')->not->toContain('100%');
})->with(array_keys(ToolPages::SLUGS));

it('promises privacy only as far as the system keeps it', function (string $key): void {
    $text = mb_strtolower(json_encode(ToolPages::page($key), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    expect($text)->not->toContain('de nadie')->not->toContain('nadie puede saber')->not->toContain('anónima de verdad')
        ->not->toContain('sin ip');

    if (str_contains($text, 'huella')) {
        expect($text)->toContain('nunca la recibe');
    }
})->with(array_keys(ToolPages::SLUGS));

it('shows real screenshots of the product, in light and dark, that exist in public/images/tools', function (string $key): void {
    $page = ToolPages::page($key);
    $shots = [...array_column($page['features'], 'shot'), ...array_column($page['steps'], 'shot')];

    foreach ($shots as $shot) {
        if (str_ends_with($shot, '-mobile')) {
            expect(public_path("images/tools/{$shot}.webp"))->toBeFile();
        } else {
            expect(public_path("images/tools/{$shot}-light.webp"))->toBeFile()
                ->and(public_path("images/tools/{$shot}-dark.webp"))->toBeFile();
        }
    }

    foreach ($page['steps'] as $step) {
        expect(trim($step['alt']))->not->toBe('');
    }

    foreach ($page['features'] as $feature) {
        expect(trim($feature['alt']))->not->toBe('')->and(trim($feature['caption']))->not->toBe('');
    }
})->with(array_keys(ToolPages::SLUGS));

it('shows the steps on three different screens, and the person on the phone in one of them', function (string $key): void {
    $shots = array_column(ToolPages::page($key)['steps'], 'shot');

    expect(array_unique($shots))->toHaveCount(3);

    if (! in_array($key, ['ley_silla', 'org_chart', 'compensation'], true)) {
        expect(array_filter($shots, fn (string $shot): bool => str_ends_with($shot, '-mobile')))->toHaveCount(1);
    }
})->with(array_keys(ToolPages::SLUGS));

it('links each page to three other tools, with the reason', function (string $key): void {
    $related = ToolPages::page($key)['related'];
    $keys = array_column($related, 'key');

    expect($keys)->toHaveCount(3)
        ->and(array_unique($keys))->toHaveCount(3)
        ->and($keys)->not->toContain($key);

    foreach ($related as $item) {
        expect(ToolPages::SLUGS)->toHaveKey($item['key'])
            ->and(trim($item['why']))->not->toBe('');
    }
})->with(array_keys(ToolPages::SLUGS));

it('gives every choice of the interactive piece its answer', function (string $key): void {
    $pick = ToolPages::page($key)['pick'];

    if ($pick === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect(count($pick['options']))->toBeGreaterThanOrEqual(2);

    foreach ($pick['options'] as $option) {
        expect(trim($option['label']))->not->toBe('')->and(trim($option['answer']))->not->toBe('')->and(trim($option['detail']))->not->toBe('');
    }
})->with(array_keys(ToolPages::SLUGS));

it('cites a source for every rule it says the law sets', function (string $key): void {
    foreach (ToolPages::page($key)['norm']['rules'] ?? [] as $rule) {
        expect(trim($rule['source']))->not->toBe('');
    }
})->with(array_keys(ToolPages::SLUGS));

it('tells search engines what each page is: the software, its questions, its film and where it sits', function (string $key, string $slug): void {
    config(['app.url' => 'https://humetria.example']);
    $page = ToolPages::page($key);
    $graph = toolJsonLd($key);

    $app = nodeOfType($graph, 'SoftwareApplication');
    $faq = nodeOfType($graph, 'FAQPage');
    $video = nodeOfType($graph, 'VideoObject');
    $crumbs = nodeOfType($graph, 'BreadcrumbList');

    expect($app)->toMatchArray(['name' => "Humetría · {$page['name']}", 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'url' => "https://humetria.example/{$slug}"])
        ->and($app)->not->toHaveKey('aggregateRating')->not->toHaveKey('review')
        ->and(array_column($faq['mainEntity'], 'name'))->toBe(array_column($page['faqs'], 'question'))
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])->toBe($page['faqs'][0]['answer'])
        ->and($video['contentUrl'])->toBe("https://humetria.example/media/films/{$page['film']['name']}.mp4")
        ->and($video['thumbnailUrl'])->toBe("https://humetria.example/video/{$page['film']['name']}.jpg")
        ->and($video['duration'])->toBe('PT'.$page['film']['duration'].'S')
        ->and($video['uploadDate'])->toBe($page['film']['uploaded'])
        ->and(array_column($crumbs['itemListElement'], 'name'))->toBe(['Inicio', $page['name']])
        ->and($crumbs['itemListElement'][1]['item'])->toBe("https://humetria.example/{$slug}");
})->with(toolPageCases());

it('names a film that exists in public/video, with the duration the landing plays', function (string $key): void {
    $film = ToolPages::page($key)['film'];
    $tools = (string) file_get_contents(resource_path('js/components/landing/landing-tools.ts'));

    expect(public_path("video/{$film['name']}.mp4"))->toBeFile()
        ->and(public_path("video/{$film['name']}.webm"))->toBeFile()
        ->and(public_path("video/{$film['name']}.jpg"))->toBeFile()
        ->and(preg_match("/name: '{$film['name']}',\\s*timing: \\{[^}]*duration: ([0-9.]+)/", $tools, $match))->toBe(1)
        ->and($film['duration'])->toBe((int) round((float) $match[1]));
})->with(array_keys(ToolPages::SLUGS));

it('shows the guide on the workday of 2027 at its address, linked from the attendance page', function (): void {
    config(['app.url' => 'https://humetria.example']);

    $this->get('/guias/registro-de-jornada-2027')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('guide')
            ->where('seo', PageSeo::guide())
            ->where('seo.canonical', 'https://humetria.example/guias/registro-de-jornada-2027')
            ->where('guide.h1', ToolPages::guide()['h1'])
            ->etc());

    $guide = ToolPages::guide();
    $graph = json_decode(PageSeo::guide()['jsonLd'], true, flags: JSON_THROW_ON_ERROR);

    expect(mb_strlen(PageSeo::guide()['title']))->toBeLessThanOrEqual(60)
        ->and(mb_strlen(PageSeo::guide()['description']))->toBeLessThanOrEqual(155)
        ->and(count($guide['sections']))->toBeGreaterThanOrEqual(3)
        ->and(nodeOfType($graph, 'Article')['headline'])->toBe($guide['h1'])
        ->and(array_column(nodeOfType($graph, 'BreadcrumbList')['itemListElement'], 'name'))->toBe(['Inicio', 'Registro de jornada', 'Guía 2027']);

    foreach ($guide['sections'] as $section) {
        expect($section['sources'])->not->toBeEmpty();
    }
});

it('serves every tool page and the guide to anyone, with GET only and without a session', function (): void {
    foreach ([...ToolPages::routes(), ToolPages::GUIDE_ROUTE] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route?->methods())->toContain('GET')
            ->and($route?->gatherMiddleware())->not->toContain('auth');
    }
});

it('lets search engines index them and renders them in Node', function (): void {
    foreach (array_keys(ToolPages::SLUGS) as $key) {
        expect(IndexablePages::includes(ToolPages::route($key)))->toBeTrue()
            ->and(SsrPages::ALLOWED[ToolPages::route($key)] ?? null)->toBe('tool');
    }

    expect(IndexablePages::includes(ToolPages::GUIDE_ROUTE))->toBeTrue()
        ->and(SsrPages::ALLOWED[ToolPages::GUIDE_ROUTE] ?? null)->toBe('guide');
});

it('measures the tool pages and the guide as it measures the landing', function (string $uri): void {
    config(['services.posthog.project_token' => 'phc_'.str_repeat('a', 40), 'services.posthog.consent_default' => 'granted']);
    $request = Request::create($uri);
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    expect(Analytics::projectToken($request))->not->toBeNull()
        ->and(Analytics::errorToken($request))->toBeNull();
})->with(['/registro-de-jornada', '/nom-035', '/guias/registro-de-jornada-2027']);

it('remembers where a visitor of a tool page came from, an ad click included, for the demo they ask for', function (): void {
    config(['services.posthog.consent_default' => 'granted']);

    $this->get('/registro-de-jornada?utm_source=google&utm_medium=cpc&utm_campaign=checador')->assertOk();

    expect(session('demo_attribution'))->toMatchArray(['source' => 'google', 'campaign' => 'checador']);
});

it('sends whoever asked for a demo from a tool page back to that page, and anything else to the landing', function (?string $page, string $back): void {
    $this->post(route('demo-requests.store'), [
        'name' => 'Ana Prueba', 'email' => 'ana@empresa.mx', 'whatsapp' => '55 1234 5678', 'company' => 'Empresa Ejemplo',
        'profile' => 'company', 'workforce' => '51_100', 'tools' => ['attendance'], 'form_extra' => '',
        ...($page === null ? [] : ['page' => $page]),
    ])->assertRedirect(route($back).'#agenda-una-demo');

    expect(DemoRequest::query()->count())->toBe(1);
})->with([
    'a tool page' => ['tools.attendance', 'tools.attendance'],
    'the guide' => ['guides.workday-2027', 'guides.workday-2027'],
    'the landing' => ['home', 'home'],
    'no page' => [null, 'home'],
    'a page that is not public' => ['dashboard', 'home'],
    'nonsense' => ['../../admin', 'home'],
]);

it('answers 404 to a tool that does not exist', function (): void {
    $this->get('/registro-de-asistencia')->assertNotFound();
});
