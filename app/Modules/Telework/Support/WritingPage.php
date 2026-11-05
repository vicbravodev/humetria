<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Support\Writing\Field;
use App\Modules\Telework\Support\Writing\Question;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Telework\Support\Writing\WrittenTemplate;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * What the page that writes a NOM-037 document with questions shows (boards V2-Politica and V3-Doc-Riesgos): the questions,
 * the answers kept so far and the question it was on, the text they make and the incisos of the norm it covers.
 */
final readonly class WritingPage
{
    public function __construct(private Writer $writer, private Companies $companies) {}

    /**
     * @param  array{href: string, label: string}  $back  Where «Guardar y volver» leads.
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
            'back' => $back,
        ];
    }
}
