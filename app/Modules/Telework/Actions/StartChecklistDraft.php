<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Starts the next draft of a center's checklist as a copy of the latest published one (version + 1). When a draft is
 * already open, that draft is the answer: a center has one at a time.
 */
final readonly class StartChecklistDraft
{
    public function __construct(private CurrentTenant $tenant) {}

    /** @throws ValidationException when the center has neither a draft nor a published version. */
    public function handle(string $workCenterId): Checklist
    {
        return DB::transaction(function () use ($workCenterId): Checklist {
            // Locking the newest published row serializes two starts at once: the second waits, then finds the first one's draft
            // instead of hitting the one-draft-per-center index.
            $latest = Checklist::query()->where('work_center_id', $workCenterId)->where('status', ChecklistStatus::Published)->orderByDesc('version')->lockForUpdate()->first();
            $draft = Checklist::query()->where('work_center_id', $workCenterId)->where('status', ChecklistStatus::Draft)->lockForUpdate()->first();

            if ($draft !== null) {
                return $draft;
            }

            $latest ??= throw ValidationException::withMessages(['checklist' => 'Este centro aún no tiene una lista publicada que copiar.']);

            $draft = Checklist::query()->create([
                'tenant_id' => $this->tenant->id(),
                'work_center_id' => $workCenterId,
                'version' => $latest->version + 1,
                'status' => ChecklistStatus::Draft,
            ]);
            $now = now();
            DB::table('checklist_questions')->insert(ChecklistQuestion::query()->where('checklist_id', $latest->id)->orderBy('position')->get()->map(fn (ChecklistQuestion $question): array => [
                // The query builder turns the uuid into its string itself: the cast only narrows the type.
                'id' => (string) Str::uuid(), // @pest-mutate-ignore: RemoveStringCast
                'tenant_id' => $question->tenant_id,
                'checklist_id' => $draft->id,
                'section' => $question->section,
                'position' => $question->position,
                'code' => $question->code,
                'heading' => $question->heading,
                'text' => $question->text,
                'origin' => $question->origin->value,
                'favorable' => $question->favorable->value,
                'requirement' => $question->requirement?->value,
                'sensitive' => $question->sensitive,
                'base_code' => $question->base_code,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            Log::info('telework.checklist_draft_started', ['work_center_id' => $workCenterId, 'checklist_id' => $draft->id, 'version' => $draft->version]);

            return $draft;
        });
    }
}
