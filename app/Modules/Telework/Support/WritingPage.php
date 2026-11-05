<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\Writing\Field;
use App\Modules\Telework\Support\Writing\Question;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Telework\Support\Writing\WrittenTemplate;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;

/**
 * What the page that writes a NOM-037 document with questions shows (boards V2-Politica and V3-Doc-Riesgos): the questions,
 * the answers kept so far and the question it was on, the text they make, the incisos of the norm it covers and what
 * publishing it means. Also a written version as people read it.
 */
final readonly class WritingPage
{
    public function __construct(private Writer $writer, private Companies $companies, private WorkCenters $workCenters, private AckAudience $audience) {}

    /**
     * @param  array{href: string, label: string, center: string|null}  $back  Where «Guardar y volver» leads, and the center
     *                                                                         whose route it is.
     * @return array<string, mixed>
     */
    public function props(WrittenTemplate $template, DocumentKind $kind, array $back): array
    {
        $draft = DocumentDraft::query()->where('kind', $kind)->first();
        $answers = $draft === null ? $this->writer->defaults($template) : [...$this->writer->defaults($template), ...$draft->answers];

        return [
            'document' => [
                'kind' => $kind->value,
                'numeral' => $kind->numeral(),
                'title' => $template->title(),
                'caption' => $kind === DocumentKind::Policy ? "NOM-037 · Etapa 1 · {$template->name()}" : "NOM-037 · Documentos de apoyo · {$template->name()}",
                'owner' => "{$this->companies->current()->tradeName} · todos los centros",
            ],
            'questions' => array_map(fn (Question $question): array => [
                'key' => $question->key,
                'topic' => $question->topic,
                'title' => $question->title,
                'hint' => $question->hint,
                'fields' => array_map(fn (Field $field): array => [
                    'key' => $field->key,
                    'legend' => $field->legend,
                    'shown' => $field->shown,
                    'multiple' => $field->multiple,
                    'columns' => $field->columns,
                    // Two arrays to array_map() number the result anew: the keys of the labels are never read.
                    'options' => array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($field->options), array_values($field->options)), // @pest-mutate-ignore: UnwrapArrayValues
                ], $question->fields),
            ], $template->questions()),
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
     * A written version as people read it: whose it is, its number, its day and its sections.
     *
     * @return array<string, mixed>
     */
    public function document(TeleworkDocument $document): array
    {
        $center = array_find($this->workCenters->all(), fn (WorkCenterSummary $center): bool => $center->id === $document->work_center_id);

        return [
            'title' => $this->writer->template($document->kind)?->title() ?? $document->kind->label(),
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
        $centers = array_map(fn (WorkCenterSummary $center): string => $center->id, array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
        $latest = TeleworkDocument::query()->inForce()->where('kind', $kind)->whereIn('work_center_id', $centers)->orderByDesc('created_at')->first();

        return [
            'audience' => $this->audience->headcount($kind, $centers),
            'centers' => count($centers),
            'version' => $latest === null ? null : ['number' => $latest->version, 'publishedOn' => $this->day($latest->created_at)],
        ];
    }

    /** "12 oct 2026", in the display timezone. */
    private function day(CarbonImmutable $moment): string
    {
        return str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'));
    }
}
