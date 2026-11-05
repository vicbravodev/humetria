<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\Writing\Templates;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * «Lo que tus personas deben conocer» (board V4-Difusion): the six things of 5.7 (a to f) as the rows of a matrix whose columns
 * are the centers, where each one stands in each center, how many are ready in every center and the one step to take next
 * (criterio C-52). A thing is ready in a center when it is published there and in front of its people (the page is active or a
 * physical means covered it); the results only where they apply. States and counts only: nothing here names a person.
 *
 * @phpstan-type Action array{label: string, href: string, primary: bool}
 */
final readonly class Matrix
{
    /** The rows, in the order of the board. */
    private const array ORDER = ['a', 'd', 'b', 'c', 'f', 'e'];

    private const array KINDS = ['a' => DocumentKind::Policy, 'b' => DocumentKind::ViolenceMeasures, 'c' => DocumentKind::PreventionMeasures, 'd' => DocumentKind::ComplaintMechanism, 'f' => DocumentKind::HealthEffects];

    /** The names of the board, in plain words. */
    private const array LABELS = [
        'a' => 'Política de prevención',
        'b' => 'Medidas contra la violencia laboral',
        'c' => 'Medidas de prevención y control',
        'd' => 'Cómo presentar una queja',
        'f' => 'Posibles alteraciones a la salud',
        'e' => 'Resultados de la evaluación',
    ];

    private const array CAPTIONS = [
        'a' => 'La declaración de tus compromisos, por escrito',
        'b' => 'Qué se considera violencia, qué pasa al denunciar y cómo se protege a quien denuncia',
        'c' => 'Lo que hace la empresa para prevenir: capacitación, cargas de trabajo, pausas',
        'd' => 'Por dónde presentar una queja o denuncia',
        'f' => 'Hay un borrador de Humetría. Revísalo con un médico antes de publicar',
        'e' => 'Un resumen sin datos de áreas pequeñas',
    ];

    /** What the next step says for each thing still to write. */
    private const array STEPS = [
        'a' => ['Escribe tu política de prevención', 'Al terminar se publica en tus %d centros y llega a tus personas para que confirmen que la leyeron.'],
        'd' => ['Escribe cómo presentar una queja', 'Al terminar se publica en la página de tus %d centros, junto a tu canal de quejas cuando lo abras.'],
        'b' => ['Escribe las medidas contra la violencia laboral', 'Al terminar se publica en la página de tus %d centros. Puedes cambiarlo cuando quieras; las versiones anteriores se guardan.'],
        'c' => ['Escribe las medidas de prevención y control', 'Al terminar se publica en la página de tus %d centros. Puedes cambiarlo cuando quieras; las versiones anteriores se guardan.'],
        'f' => ['Revisa el borrador de las alteraciones a la salud', 'Es un borrador de Humetría. Revísalo con un médico antes de publicarlo en tus %d centros.'],
    ];

    public function __construct(private Templates $templates, private ComplaintChannelFeed $channel, private Companies $companies) {}

    /**
     * Where each thing stands in a center: done, missing, published but not in front of the people («undelivered»), and for
     * the results also ready to publish, waiting for a closed evaluation or not applicable.
     *
     * @return array<string, string>
     */
    public static function cells(CenterFacts $facts): array
    {
        $cells = [];

        foreach (self::ORDER as $item) {
            $cells[$item] = $item === 'e' ? self::resultsState($facts->results) : self::written($facts, $item);
        }

        return $cells;
    }

    /**
     * The rows with their way in, how many things are ready in every center and the next step, from the cells of every center
     * in use.
     *
     * @param  array<string, array<string, string>>  $cells  By work center id.
     * @param  array<string, CenterFacts>  $facts  By work center id.
     * @param  array<string, WorkCenterSummary>  $centers  By work center id.
     * @return array{items: list<array<string, mixed>>, progress: array{ready: int, total: int, minutes: int}, next: array<string, mixed>|null, reach: array{page: bool, confirmed: int, people: int}}
     */
    public function of(array $cells, array $facts, array $centers): array
    {
        $tenantSlug = $this->companies->current()->slug;
        $manage = Gate::allows('manage-disclosure');
        $documents = Document::query()->whereIn('status', [DocumentStatus::Draft, DocumentStatus::Published])->orderByDesc('version')->get(['kind', 'status', 'version', 'published_at']);
        $ready = array_filter(self::ORDER, fn (string $item): bool => array_all($cells, fn (array $center): bool => in_array($center[$item], ['done', 'not_applicable'], true)));
        $next = $manage ? $this->next($tenantSlug, $cells, $ready, $documents, count($centers)) : null;
        $items = [];

        foreach (self::ORDER as $item) {
            $kind = self::KINDS[$item] ?? null;
            $latest = $kind === null ? null : $documents->first(fn (Document $document): bool => $document->kind === $kind && $document->status === DocumentStatus::Published);
            $draft = $kind !== null && $documents->contains(fn (Document $document): bool => $document->kind === $kind && $document->status === DocumentStatus::Draft);
            $action = $manage ? ($kind === null ? $this->resultsAction($tenantSlug, $cells, $facts, $centers) : $this->action($tenantSlug, $kind, $latest !== null, $draft)) : null;

            $items[] = [
                'key' => $item,
                'numeral' => match ($item) {
                    'a' => '5.1',
                    'e' => '7.8',
                    default => "5.7 {$item}",
                },
                'label' => self::LABELS[$item],
                'caption' => $this->caption($item, $latest, $draft),
                'draft' => $draft,
                'action' => $action === null ? null : [...$action, 'primary' => ($next['item'] ?? null) === $item],
            ];
        }

        $minutes = array_sum(array_map(fn (string $item): int => $this->templates->template(self::KINDS[$item])->minutes(), array_values(array_diff(array_keys(self::KINDS), $ready))));

        return [
            'items' => $items,
            'progress' => ['ready' => count($ready), 'total' => count(self::ORDER), 'minutes' => $minutes],
            'next' => $next,
            // How it reaches the people: whether their page is active, and how many confirmed the policy in force where they work.
            'reach' => [
                'page' => array_any($facts, fn (CenterFacts $fact): bool => $fact->pageActive),
                'confirmed' => array_sum(array_map(fn (CenterFacts $fact): int => $fact->acknowledgments['confirmed'] ?? 0, $facts)),
                'people' => array_sum(array_map(fn (CenterFacts $fact): int => $fact->acknowledgments['active'] ?? 0, $facts)),
            ],
        ];
    }

    private static function written(CenterFacts $facts, string $item): string
    {
        $published = $item === 'a' ? $facts->policy !== null : $facts->hasContent($item);

        return match (true) {
            ! $published => 'missing',
            ! $facts->delivered($item) => 'undelivered',
            default => 'done',
        };
    }

    /** Where the results for the people of a center stand: ready to publish, published, waiting for a closed evaluation or not applicable. */
    public static function resultsState(?ResultsFact $fact): string
    {
        return match (true) {
            $fact !== null && ! $fact->applies => 'not_applicable',
            $fact === null || $fact->latest === null => 'waiting',
            ! $fact->latestPublished => 'ready',
            default => 'done',
        };
    }

    private function caption(string $item, ?Document $latest, bool $draft): string
    {
        if ($item === 'd' && $this->channel->current() !== null) {
            return 'Se llena sola con tu canal de quejas';
        }

        return match (true) {
            $latest !== null && $latest->published_at !== null => ($item === 'a' ? 'Publicada' : 'Publicado').", versión {$latest->version} del ".Days::moment($latest->published_at),
            $draft => 'Tienes un borrador a medias',
            default => self::CAPTIONS[$item],
        };
    }

    /** @return array{label: string, href: string} */
    private function action(string $tenantSlug, DocumentKind $kind, bool $published, bool $draft): array
    {
        $write = route('tenant.disclosure.write', [$tenantSlug, $kind->value]);

        return match (true) {
            $draft => ['label' => 'Continuar', 'href' => $write],
            $published => ['label' => 'Ver', 'href' => $kind === DocumentKind::Policy ? route('tenant.disclosure.policy', $tenantSlug) : route('tenant.disclosure.contents', $tenantSlug)],
            $kind === DocumentKind::HealthEffects => ['label' => 'Revisar borrador', 'href' => $write],
            default => ['label' => "Escribir · {$this->templates->template($kind)->minutes()} min", 'href' => $write],
        };
    }

    /**
     * The results: the center whose closed evaluation waits to be published, or the evidence where they are published.
     *
     * @param  array<string, array<string, string>>  $cells
     * @param  array<string, CenterFacts>  $facts
     * @param  array<string, WorkCenterSummary>  $centers
     * @return array{label: string, href: string}|null
     */
    private function resultsAction(string $tenantSlug, array $cells, array $facts, array $centers): ?array
    {
        $ready = array_keys(array_filter($cells, fn (array $center): bool => $center['e'] === 'ready'));
        $campaign = count($ready) === 1 ? $facts[$ready[0]]->results?->latest : null;

        return match (true) {
            $campaign !== null => ['label' => "Publicar {$centers[$ready[0]]->name}", 'href' => route('tenant.disclosure.results.share', [$tenantSlug, $campaign->id])],
            $ready !== [] => ['label' => 'Publicar resultados', 'href' => route('tenant.disclosure.dissemination', $tenantSlug)],
            default => null,
        };
    }

    /**
     * The one step to take now, for the first thing that is not ready in every center, in the order of the board: write it
     * where it is missing, put it in front of the people where it is out of sight, or publish the results ready to publish.
     *
     * @param  array<string, array<string, string>>  $cells
     * @param  array<int, string>  $ready
     * @param  Collection<int, Document>  $documents
     * @return array{item: string, title: string, text: string, action: array{label: string, href: string}}|null
     */
    private function next(string $tenantSlug, array $cells, array $ready, Collection $documents, int $centers): ?array
    {
        $item = array_find(self::ORDER, fn (string $item): bool => ! in_array($item, $ready, true));

        if ($item === null) {
            return null;
        }

        $has = fn (string $state): bool => array_any($cells, fn (array $center): bool => $center[$item] === $state);
        $kind = self::KINDS[$item] ?? null;

        $step = self::STEPS[$item] ?? null;

        if ($kind !== null && $step !== null && $has('missing')) {
            $questions = count($this->templates->template($kind)->questions());
            $drafting = $documents->contains(fn (Document $document): bool => $document->kind === $kind && $document->status === DocumentStatus::Draft);

            return [
                'item' => $item,
                'title' => $step[0],
                'text' => match ($questions) {
                    0 => '',
                    1 => '1 pregunta con opciones. ',
                    default => "{$questions} preguntas con opciones. ",
                }.sprintf($step[1], $centers),
                'action' => ['label' => $drafting ? 'Continuar' : 'Empezar', 'href' => route('tenant.disclosure.write', [$tenantSlug, $kind->value])],
            ];
        }

        return match (true) {
            $has('undelivered') => ['item' => $item, 'title' => 'Pon a la vista de tus personas lo que publicaste', 'text' => 'Aún no lo pueden consultar: activa la página de las personas o registra un cartel, boletín o folleto que lo cubra.', 'action' => ['label' => 'Activar su página', 'href' => route('tenant.disclosure.page', $tenantSlug)]],
            $item === 'e' && $has('ready') => ['item' => 'e', 'title' => 'Publica los resultados para tus personas', 'text' => 'Verán cuántas personas contestaron y los temas que más atención necesitan; nunca datos de áreas o grupos con menos de 5 personas.', 'action' => ['label' => 'Publicar', 'href' => route('tenant.disclosure.dissemination', $tenantSlug)]],
            default => null,
        };
    }
}
