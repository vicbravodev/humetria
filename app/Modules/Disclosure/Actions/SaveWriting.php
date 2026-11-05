<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Support\Writing\Writer;
use App\Support\Writing\WrittenTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Keeps what RH answered while writing the policy or a block of 5.7 with questions (boards V2-N035-Politica and
 * V4-Difusion-Contenidos: «Se guarda en cada respuesta»), the question it is on and the sections it rewrote, in the draft of
 * that kind; the draft's text is what they make. The first save opens the draft from the answers of the version published
 * last, or else from what the questions mark at first. Publishing is the publication that already exists (criterio C-50).
 * Logged by kind and step only.
 */
final readonly class SaveWriting
{
    public function __construct(private CurrentTenant $tenant, private Writer $writer) {}

    /**
     * @param  array<array-key, mixed>|null  $answers  The answers sent, by field; null when only the step changes.
     * @param  int|null  $step  The question it is on; the number of questions is the review.
     * @param  array{section: string, text: string}|null  $edit  A section rewritten; an empty text puts back the answers'.
     *
     * @throws ValidationException
     */
    public function handle(WrittenTemplate $template, DocumentKind $kind, ?array $answers, ?int $step, int $userId, ?array $edit = null): Document
    {
        if ($step !== null && ($step < 0 || $step > count($template->questions()))) {
            throw ValidationException::withMessages(['step' => 'Esa pregunta no existe.']);
        }

        $tenantId = $this->tenant->id();

        return DB::transaction(function () use ($template, $kind, $answers, $step, $userId, $edit, $tenantId): Document {
            // Two saves of the same kind take turns, so the first one never opens two drafts.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["disclosure_writing:{$tenantId}:{$kind->value}"]);
            $draft = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Draft)->first();
            $start = $draft ?? Document::query()->where('kind', $kind)->where('status', DocumentStatus::Published)->whereNotNull('answers')->orderByDesc('version')->first();
            $current = [...$this->writer->defaults($template), ...($start->answers ?? [])];
            $merged = $answers === null ? $current : $this->writer->merge($template, $current, $answers);
            $edits = $this->writer->standing($template, $start->edits ?? [], $current, $merged);
            $edits = $edit === null ? $edits : $this->writer->rewrite($template, $edits, $edit);

            $written = [
                'answers' => $merged,
                'edits' => $edits,
                'step' => $step ?? $draft->step ?? 0,
                'content' => array_map(fn (array $section): array => ['key' => $section['key'], 'heading' => $section['heading'], 'text' => $section['text'] ?? '', 'items' => [], 'list_style' => null], $this->writer->sections($template, $merged, $edits)),
                'template_version' => $template->version(),
            ];

            if ($draft === null) {
                $draft = Document::query()->create(['tenant_id' => $tenantId, 'kind' => $kind, 'status' => DocumentStatus::Draft, 'created_by' => $userId, ...$written]);
            } else {
                $draft->update($written);
            }

            Log::info('disclosure.writing_saved', ['kind' => $kind->value, 'step' => $draft->step]);

            return $draft;
        });
    }
}
