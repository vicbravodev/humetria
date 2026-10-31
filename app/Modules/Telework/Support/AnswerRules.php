<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Data\Favorable;
use App\Modules\Telework\Models\ChecklistQuestion;

/**
 * What a person must and must not send when they answer a checklist (criterios C-10 and C-25). Pure: it judges, it stores
 * nothing.
 *
 * - Every question needs an answer (yes, no or not_applicable), except the sensitive ones when the consent is declined:
 *   those stay unanswered, and then there are no photos and no special-needs note either.
 * - A risk note is required exactly when the answer is the unfavorable one of a question that is not informative.
 */
final readonly class AnswerRules
{
    /** @var list<string> */
    public const ANSWERS = ['yes', 'no', 'not_applicable'];

    /** The key of the photos that belong to the whole list and not to one question. */
    public const GENERAL = 'general';

    public static function unfavorable(Favorable $favorable, string $answer): bool
    {
        return ($favorable === Favorable::Yes && $answer === 'no') || ($favorable === Favorable::No && $answer === 'yes');
    }

    /**
     * @param  list<ChecklistQuestion>  $questions  The questions of the application's checklist version and section.
     * @param  array<string, ?string>  $answers
     * @param  array<string, string>  $riskNotes
     * @param  array<string, list<string>>  $photos
     * @return array<string, string> Message by field, empty when the submission is acceptable.
     */
    public function errors(array $questions, bool $consent, array $answers, array $riskNotes, ?string $specialNeeds, array $photos): array
    {
        $ids = array_map(fn (ChecklistQuestion $question): string => $question->id, $questions);
        $errors = [];

        if (array_diff(array_keys($answers), $ids) !== []) {
            $errors['answers'] = 'Hay respuestas que no son de esta lista.';
        }

        if (array_diff(array_keys($riskNotes), $ids) !== []) {
            $errors['risk_notes'] = 'Hay notas que no son de esta lista.';
        }

        $photos = array_filter($photos);

        if (array_diff(array_keys($photos), [self::GENERAL, ...$ids]) !== []) {
            $errors['photos'] = 'Hay fotos que no son de esta lista.';
        }

        foreach ($questions as $question) {
            $errors = [...$errors, ...$this->questionErrors($question, $consent, $answers[$question->id] ?? null, trim($riskNotes[$question->id] ?? ''))];
        }

        if (! $consent) {
            $errors += $this->declinedErrors($specialNeeds, $photos);
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function questionErrors(ChecklistQuestion $question, bool $consent, ?string $answer, string $note): array
    {
        $answerKey = "answers.{$question->id}";
        $noteKey = "risk_notes.{$question->id}";

        if ($answer === null) {
            return match (true) {
                $question->sensitive && ! $consent => $note === '' ? [] : [$noteKey => 'Esta pregunta no lleva nota.'],
                default => [$answerKey => 'Conteste esta pregunta.'],
            };
        }

        if ($question->sensitive && ! $consent) {
            return [$answerKey => 'Esta pregunta solo se contesta si acepta el consentimiento.'];
        }

        if (! in_array($answer, self::ANSWERS, true)) {
            return [$answerKey => 'La respuesta no es válida.'];
        }

        $needsNote = self::unfavorable($question->favorable, $answer);

        return match (true) {
            $needsNote && $note === '' => [$noteKey => 'Describa el riesgo que encontró.'],
            ! $needsNote && $note !== '' => [$noteKey => 'Esta respuesta no lleva nota de riesgo.'],
            default => [],
        };
    }

    /**
     * @param  array<string, list<string>>  $photos
     * @return array<string, string>
     */
    private function declinedErrors(?string $specialNeeds, array $photos): array
    {
        $errors = [];

        if (trim($specialNeeds ?? '') !== '') {
            $errors['special_needs'] = 'La necesidad especial se comparte solo si acepta el consentimiento.';
        }

        if ($photos !== []) {
            $errors['photos'] = 'Las fotos se suben solo si acepta el consentimiento.';
        }

        return $errors;
    }
}
