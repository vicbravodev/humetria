<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\QuestionOrigin;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\ChecklistOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Edits a question of the draft. Changing its text takes it off the DOF or Humetría: a DOF one becomes `dof_modified`
 * (keeping its base code), a Humetría one becomes the Comisión's. The answer, sensitivity and requirement never change origin.
 */
final readonly class UpdateQuestion
{
    /** @throws ValidationException when the checklist is already published. */
    public function handle(ChecklistQuestion $question, string $text, Favorable $favorable, ?Requirement $requirement, bool $sensitive): ChecklistQuestion
    {
        return ChecklistOrder::editing($question->checklist_id, function (Checklist $checklist) use ($question, $text, $favorable, $requirement, $sensitive): ChecklistQuestion {
            $question->refresh();
            $edited = $text !== $question->text;

            $question->update([
                'text' => $text,
                'favorable' => $favorable,
                'requirement' => $requirement,
                'sensitive' => $sensitive,
                'origin' => $edited ? $this->originAfterEdit($question->origin) : $question->origin,
            ]);
            Log::info('telework.question_updated', ['checklist_id' => $checklist->id, 'question_id' => $question->id, 'text_changed' => $edited]);

            return $question;
        });
    }

    private function originAfterEdit(QuestionOrigin $origin): QuestionOrigin
    {
        return match ($origin) {
            QuestionOrigin::Dof, QuestionOrigin::DofModified => QuestionOrigin::DofModified,
            QuestionOrigin::Humetria, QuestionOrigin::Committee => QuestionOrigin::Committee,
        };
    }
}
