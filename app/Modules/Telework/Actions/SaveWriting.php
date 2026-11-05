<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Models\DocumentDraft;
use App\Modules\Telework\Support\Writing\Writer;
use App\Modules\Telework\Support\Writing\WrittenTemplate;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Keeps what RH answered while writing a NOM-037 document with questions, the question it is on (board V2-Politica: «Se
 * guarda en cada respuesta») and the sections it rewrote. The first save starts the draft from what the board marks; a
 * question that gets another answer drops the rewritten text of its section. Logged by kind and step only.
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
    public function handle(WrittenTemplate $template, DocumentKind $kind, ?array $answers, ?int $step, int $userId, ?array $edit = null): DocumentDraft
    {
        if ($step !== null && ($step < 0 || $step > count($template->questions()))) {
            throw ValidationException::withMessages(['step' => 'Esa pregunta no existe.']);
        }

        $tenantId = $this->tenant->id();

        return DB::transaction(function () use ($template, $kind, $answers, $step, $userId, $edit, $tenantId): DocumentDraft {
            // Two saves of the same document take turns, so the first one never starts two drafts.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["telework_document_drafts:{$tenantId}:{$kind->value}"]);
            $draft = DocumentDraft::query()->where('kind', $kind)->first();
            $current = $draft === null ? $this->writer->defaults($template) : $draft->answers;
            $merged = $answers === null ? $current : $this->writer->merge($template, $current, $answers);
            $edits = $this->writer->standing($template, $draft->edits ?? [], $current, $merged);

            $draft = DocumentDraft::query()->updateOrCreate(['kind' => $kind], [
                'tenant_id' => $tenantId,
                'answers' => $merged,
                'edits' => $edit === null ? $edits : $this->writer->rewrite($template, $edits, $edit),
                'step' => $step ?? $draft->step ?? 0,
                'updated_by' => $userId,
            ]);
            Log::info('telework.writing_saved', ['kind' => $kind->value, 'step' => $draft->step]);

            return $draft;
        });
    }
}
