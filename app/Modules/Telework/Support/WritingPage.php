<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\Writing\Templates;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Support\Writing\Writer;
use App\Support\Writing\WrittenTemplate;
use Carbon\CarbonImmutable;

/**
 * What the page that writes a NOM-037 document with questions shows (boards V2-Politica and V3-Doc-Riesgos): the questions,
 * the answers kept so far and the question it was on, the text they make, the incisos of the norm it covers and what
 * publishing it means. Also a written version as people read it.
 */
final readonly class WritingPage
{
    public function __construct(private Writer $writer, private Templates $templates, private Companies $companies, private WorkCenters $workCenters, private AckAudience $audience) {}

    /**
     * @param  array{href: string, label: string, center: string|null}  $back  Where «Guardar y volver» leads, and the center
     *                                                                         whose route it is.
     * @return array<string, mixed>
     */
    public function props(WrittenTemplate $template, DocumentKind $kind, array $back): array
    {
        $draft = DocumentDraft::query()->where('kind', $kind)->first();
        $center = $back['center'] === null ? null : $this->workCenters->findActive($back['center']);
        $answers = $draft === null ? $this->writer->defaults($template) : [...$this->writer->defaults($template), ...$draft->answers];

        return [
            'document' => [
                'kind' => $kind->value,
                'numeral' => $kind->numeral(),
                'title' => $template->title(),
                'caption' => $kind === DocumentKind::Policy ? "NOM-037 · Etapa 1 · {$template->name()}" : "NOM-037 · Documentos de apoyo · {$template->name()}",
                'owner' => "{$this->companies->current()->tradeName} · ".($center->name ?? 'todos los centros'),
            ],
            'questions' => $this->writer->questions($template),
            'answers' => $answers,
            'step' => $draft->step ?? 0,
            'sections' => $this->writer->sections($template, $answers, $draft->edits ?? []),
            'items' => array_map(fn (string $key, string $label): array => ['key' => $key, 'label' => $label], array_keys($kind->items()), array_values($kind->items())), // @pest-mutate-ignore: UnwrapArrayValues
            'minutes' => $template->minutes(),
            'publication' => $this->publication($kind),
            'back' => $back,
        ];
    }

    /**
     * The six supporting documents (board V2-N037-Docs): what each is, how long its questions take, whether it is in force
     * in every center in use (uploaded or written) with its latest version, and whether its writing started.
     *
     * @return array{documents: list<array<string, mixed>>, centers: int}
     */
    public function supporting(): array
    {
        $centers = $this->activeCenters();
        $inForce = TeleworkDocument::query()->inForce()->whereIn('kind', TeleworkPages::SUPPORT)->whereIn('work_center_id', $centers)
            ->orderBy('created_at')->get(['id', 'kind', 'work_center_id', 'version', 'created_at'])->groupBy(fn (TeleworkDocument $document): string => $document->kind->value);
        $started = DocumentDraft::query()->whereIn('kind', TeleworkPages::SUPPORT)->get(['kind'])->map(fn (DocumentDraft $draft): string => $draft->kind->value)->all();

        return [
            'documents' => array_map(function (DocumentKind $kind) use ($inForce, $started, $centers): array {
                $template = $this->templates->template($kind);
                $versions = $inForce->get($kind->value);
                $latest = $versions?->last();

                return [
                    'kind' => $kind->value,
                    'name' => $template?->name() ?? $kind->label(),
                    'summary' => $template?->summary() ?? '',
                    'numeral' => $kind->numeral(),
                    'minutes' => $template?->minutes() ?? 0,
                    'ready' => $centers !== [] && $versions?->count() === count($centers),
                    'started' => in_array($kind->value, $started, true),
                    'version' => $latest === null ? null : ['id' => $latest->id, 'number' => $latest->version, 'publishedOn' => $this->day($latest->created_at)],
                ];
            }, TeleworkPages::SUPPORT),
            'centers' => count($centers),
        ];
    }

    /**
     * A written version as people read it: whose it is, its number, its day and its sections.
     *
     * @return array<string, mixed>
     */
    public function document(TeleworkDocument $document): array
    {
        $center = array_find($this->workCenters->all(), fn (WorkCenterSummary $center): bool => $center->id === $document->work_center_id);

        return [
            'title' => $this->templates->template($document->kind)?->title() ?? $document->kind->label(),
            'company' => $this->companies->current()->tradeName,
            'owner' => $this->companies->current()->tradeName.($center === null ? '' : " · {$center->name}"),
            'version' => $document->version,
            'publishedOn' => $this->day($document->created_at),
            'sections' => array_map(fn (array $section): array => ['heading' => $section['heading'], 'text' => $section['text']], $document->body ?? []),
        ];
    }

    /**
     * Who a new version reaches: the confirmations it asks for (null for a kind nobody confirms), the centers in use and the
     * version in force there, if any.
     *
     * @return array{audience: int|null, centers: int, version: array{number: int, publishedOn: string}|null}
     */
    private function publication(DocumentKind $kind): array
    {
        $centers = $this->activeCenters();
        $latest = TeleworkDocument::query()->inForce()->where('kind', $kind)->whereIn('work_center_id', $centers)->orderByDesc('created_at')->first();

        return [
            'audience' => $this->audience->headcount($kind, $centers),
            'centers' => count($centers),
            'version' => $latest === null ? null : ['number' => $latest->version, 'publishedOn' => $this->day($latest->created_at)],
        ];
    }

    /** @return list<string> The ids of the centers in use. */
    private function activeCenters(): array
    {
        return array_map(fn (WorkCenterSummary $center): string => $center->id, array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
    }

    /** "12 oct 2026", in the display timezone. */
    private function day(CarbonImmutable $moment): string
    {
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'));
    }
}
