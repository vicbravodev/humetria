<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Support\Writing\Writer;
use App\Support\Writing\WrittenTemplate;

/**
 * What the pages that write the policy or a block of 5.7 with questions receive (boards V2-N035-Politica and
 * V4-Difusion-Contenidos): the questions, the answers of the draft (or of the version published last, where the next one
 * starts), the text they make with the company's name in it, and what publishing needs: the draft and the centers in use.
 * The words are the company's own; nothing here points at a person.
 */
final readonly class WritingPages
{
    public function __construct(private Writer $writer, private Companies $companies, private WorkCenters $workCenters) {}

    /**
     * @param  array{href: string, label: string, center: string|null, centerName: string|null}  $back  Where «Guardar y volver» leads, and the center
     *                                                                                                  whose route it is.
     * @return array<string, mixed>
     */
    public function props(WrittenTemplate $template, DocumentKind $kind, array $back): array
    {
        $draft = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Draft)->first();
        $latest = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Published)->orderByDesc('version')->first();
        $start = $draft ?? ($latest?->answers === null ? null : $latest);
        $answers = [...$this->writer->defaults($template), ...($start->answers ?? [])];
        $company = $this->companies->current()->tradeName;

        return [
            'document' => [
                'kind' => $kind->value,
                'inciso' => $kind->inciso(),
                'title' => $template->title(),
                'caption' => $kind === DocumentKind::Policy ? "NOM-035 · Etapa 1 · {$template->name()}" : "NOM-035 · Difusión · {$template->name()}",
                'owner' => "{$company} · todos los centros",
                'company' => $company,
            ],
            'questions' => $this->writer->questions($template),
            'answers' => $answers,
            'step' => $draft->step ?? 0,
            'sections' => array_map(fn (array $section): array => [...$section, 'text' => $section['text'] === null ? null : str_replace('{legal_name}', $company, $section['text'])], $this->writer->sections($template, $answers, $start->edits ?? [])),
            'minutes' => $template->minutes(),
            'publication' => [
                'draftId' => $draft?->id,
                'centersInUse' => count(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)),
                'version' => $latest === null || $latest->version === null || $latest->published_at === null ? null : ['number' => $latest->version, 'publishedOn' => Days::moment($latest->published_at)],
            ],
            'back' => $back,
        ];
    }
}
