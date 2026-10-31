<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\QuestionOrigin;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds a Comisión question at the end of a section of the draft. Its code is `C1-nn` or `C2-nn`, the next free number of
 * that section; it never claims to come from the DOF or from Humetría (criterio C-07).
 */
final readonly class AddQuestion
{
    /** @throws ValidationException when the checklist is already published. */
    public function handle(string $checklistId, int $section, string $heading, string $text, Favorable $favorable, ?Requirement $requirement, bool $sensitive): ChecklistQuestion
    {
        return ChecklistOrder::editing($checklistId, function (Checklist $checklist) use ($section, $heading, $text, $favorable, $requirement, $sensitive): ChecklistQuestion {
            $questions = ChecklistQuestion::query()->where('checklist_id', $checklist->id)->orderBy('position')->get(['id', 'section', 'position', 'code']);
            $prefix = "C{$section}-";
            // Never reuse a code of the center's lineage: older versions (and a question taken out of this draft) keep theirs.
            $last = ChecklistQuestion::query()
                ->whereHas('checklist', fn (Builder $checklists) => $checklists->where('work_center_id', $checklist->work_center_id))
                ->where('code', 'like', $prefix.'%')->get(['code'])
                ->map(fn (ChecklistQuestion $question): int => (int) substr($question->code, strlen($prefix)))->max() ?? 0;

            $added = ChecklistQuestion::query()->create([
                'tenant_id' => $checklist->tenant_id,
                'checklist_id' => $checklist->id,
                'section' => $section,
                'position' => $questions->count() + 1,
                'code' => $prefix.str_pad((string) ($last + 1), 2, '0', STR_PAD_LEFT),
                'heading' => $heading,
                'text' => $text,
                'origin' => QuestionOrigin::Committee,
                'favorable' => $favorable,
                'requirement' => $requirement,
                'sensitive' => $sensitive,
                'base_code' => null,
            ]);

            // After the last question of its section.
            $order = $questions->map(fn (ChecklistQuestion $question): string => $question->id)->all();
            $after = $questions->where('section', '<=', $section)->count();
            array_splice($order, $after, 0, [$added->id]);
            ChecklistOrder::renumber($checklist, $order);

            Log::info('telework.question_added', ['checklist_id' => $checklist->id, 'question_id' => $added->id, 'code' => $added->code]);

            return $added;
        });
    }
}
