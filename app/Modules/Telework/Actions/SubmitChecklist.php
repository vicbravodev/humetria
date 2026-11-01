<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Support\AnswerRules;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\Settings;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A person sends the answers of one checklist application, once and whole (criterios C-09, C-10, C-25). The application
 * keeps the version it was created with: only its questions are asked, whatever the Comisión published since.
 *
 * Photos arrive already stored (`PhotoStore`), as paths: this action does not know HTTP. Whatever happens, a submission
 * that is not saved leaves none of its photos behind.
 */
final readonly class SubmitChecklist
{
    public function __construct(private AnswerRules $rules, private Settings $settings, private FileStore $files) {}

    /**
     * @param  array<string, ?string>  $answers  By question id.
     * @param  array<string, string>  $riskNotes  By question id.
     * @param  array<string, list<string>>  $photos  Stored paths, by question id; `general` holds those of the whole list.
     *
     * @throws ModelNotFoundException when the application is not one of this person's workplaces.
     * @throws ValidationException when the submission breaks the rules or the application was already sent.
     */
    public function handle(OpenedPortal $portal, string $applicationId, bool $consent, array $answers, array $riskNotes, ?string $specialNeeds, array $photos): void
    {
        try {
            DB::transaction(fn () => $this->submit($portal, $applicationId, $consent, $answers, $riskNotes, $specialNeeds, $photos));
        } catch (Throwable $failure) {
            $this->files->delete(...array_merge(...array_values($photos)));

            throw $failure;
        }
    }

    /**
     * @param  array<string, ?string>  $answers
     * @param  array<string, string>  $riskNotes
     * @param  array<string, list<string>>  $photos
     */
    private function submit(OpenedPortal $portal, string $applicationId, bool $consent, array $answers, array $riskNotes, ?string $specialNeeds, array $photos): void
    {
        $teleworkerId = $portal->teleworkerId ?? throw new ModelNotFoundException;
        // Locked: two sends of the same list at once cannot both pass the check below.
        $application = ChecklistApplication::query()
            ->ownedBy($teleworkerId)
            ->lockForUpdate()
            ->findOrFail($applicationId);

        if ($application->submitted_at !== null) {
            throw ValidationException::withMessages(['application' => 'Esta lista ya se envió.']);
        }

        // A fresh get() is already a list.
        $questions = array_values(ChecklistQuestion::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->where('checklist_id', $application->checklist_id)->where('section', $application->section->value)->orderBy('position')->get()->all());
        $errors = $this->rules->errors($questions, $consent, $answers, $riskNotes, $specialNeeds, $photos);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $now = now();
        $this->insertAnswers($application, $questions, $answers, $riskNotes, $now);
        $count = $this->insertPhotos($application, $photos, $now);

        $application->update([
            'submitted_at' => $now,
            'consent_id' => $consent ? $this->settings->currentConsent()->id : null,
            'consent_accepted_at' => $consent ? $now : null,
            'special_needs' => trim($specialNeeds ?? '') ?: null,
        ]);

        // Ids and counts only: never an answer, a note or the special-needs text.
        Log::info('telework.checklist_submitted', [
            'application_id' => $application->id,
            'workplace_id' => $application->workplace_id,
            'checklist_id' => $application->checklist_id,
            'section' => $application->section->value,
            'answer_count' => count($questions),
            'photo_count' => $count,
            'consent_accepted' => $consent,
        ]);
    }

    /**
     * @param  list<ChecklistQuestion>  $questions
     * @param  array<string, ?string>  $answers
     * @param  array<string, string>  $riskNotes
     */
    private function insertAnswers(ChecklistApplication $application, array $questions, array $answers, array $riskNotes, DateTimeInterface $now): void
    {
        DB::table('checklist_answers')->insert(array_map(fn (ChecklistQuestion $question): array => [
            // The query builder turns the uuid into its string itself: the cast only narrows the type.
            'id' => (string) Str::uuid(), // @pest-mutate-ignore: RemoveStringCast
            'tenant_id' => $application->tenant_id,
            'application_id' => $application->id,
            'question_id' => $question->id,
            'answer' => $answers[$question->id] ?? null,
            'risk_note' => trim($riskNotes[$question->id] ?? '') ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $questions));
    }

    /**
     * @param  array<string, list<string>>  $photos
     * @return int How many photos were recorded.
     */
    private function insertPhotos(ChecklistApplication $application, array $photos, DateTimeInterface $now): int
    {
        $rows = [];

        foreach ($photos as $key => $paths) {
            foreach ($paths as $path) {
                $rows[] = [
                    'id' => (string) Str::uuid(), // @pest-mutate-ignore: RemoveStringCast
                    'tenant_id' => $application->tenant_id,
                    'application_id' => $application->id,
                    'question_id' => $key === AnswerRules::GENERAL ? null : $key,
                    'path' => $path,
                    'uploaded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('checklist_photos')->insert($rows);

        return count($rows);
    }
}
