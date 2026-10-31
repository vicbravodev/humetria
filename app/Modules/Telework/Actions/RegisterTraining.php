<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * RH registers a training given at a work center (numerals 5.9 and 8.1, criterio C-24): the day, the modality, the 8.1
 * topics it covered, and who attended (active people of that center). It only records: delivering the training is not
 * Humetría's job. The optional list of attendance (PDF) goes to the private disk. Nothing is edited or deleted.
 */
final readonly class RegisterTraining
{
    public function __construct(private CurrentTenant $tenant, private WorkCenters $workCenters, private Roster $roster, private FileStore $files, private RecordAudit $audit) {}

    /**
     * @param  list<TrainingTopic>  $topics
     * @param  list<string>  $employeeIds
     * @param  string|null  $evidenceFile  Path of the uploaded PDF, already validated; it is copied to the private disk here.
     *
     * @throws ValidationException when the center is not one in use, a list is empty or an attendee is not an active person of the center.
     */
    public function handle(string $workCenterId, CarbonImmutable $heldOn, TrainingModality $modality, array $topics, array $employeeIds, ?string $evidenceFile, int $userId): Training
    {
        $attendees = array_values(array_unique($employeeIds));
        $this->check($workCenterId, $topics, $attendees);

        $tenantId = $this->tenant->id();
        $path = "trainings/{$tenantId}/".Str::uuid().'.pdf';

        try {
            return DB::transaction(function () use ($workCenterId, $heldOn, $modality, $topics, $attendees, $evidenceFile, $path, $tenantId, $userId): Training {
                if ($evidenceFile !== null) {
                    $this->store($evidenceFile, $path);
                }

                $training = Training::query()->create([
                    'tenant_id' => $tenantId,
                    'work_center_id' => $workCenterId,
                    'held_on' => $heldOn,
                    'modality' => $modality,
                    'topics' => array_values(array_unique(array_map(fn (TrainingTopic $topic): string => $topic->value, $topics))),
                    'evidence_path' => $evidenceFile === null ? null : $path,
                    'registered_by' => $userId,
                ]);

                foreach ($attendees as $employeeId) {
                    TrainingAttendee::query()->create(['tenant_id' => $tenantId, 'training_id' => $training->id, 'employee_id' => $employeeId]);
                }

                // Ids and counts only: who attended is in the table, never in a log.
                $record = ['training_id' => $training->id, 'work_center_id' => $workCenterId, 'topic_count' => count($training->topics), 'attendee_count' => count($attendees)];
                $this->audit->handle('telework.training_registered', $training, $record);
                Log::info('telework.training_registered', $record);

                return $training;
            });
        } catch (Throwable $failure) {
            // The file went in before the commit: a failed registration must not leave it behind.
            $this->files->delete($path);

            throw $failure;
        }
    }

    /**
     * @param  list<TrainingTopic>  $topics
     * @param  list<string>  $attendees
     */
    private function check(string $workCenterId, array $topics, array $attendees): void
    {
        if (! Str::isUuid($workCenterId) || $this->workCenters->findActive($workCenterId) === null) {
            throw ValidationException::withMessages(['work_center_id' => 'Elige un centro de trabajo de la empresa.']);
        }

        if ($topics === []) {
            throw ValidationException::withMessages(['topics' => 'Elige al menos un tema.']);
        }

        if ($attendees === []) {
            throw ValidationException::withMessages(['employee_ids' => 'Elige al menos a una persona que asistió.']);
        }

        if (array_diff($attendees, $this->roster->activeIdsIn($workCenterId)) !== []) {
            throw ValidationException::withMessages(['employee_ids' => 'Solo pueden asistir personas activas de este centro de trabajo.']);
        }
    }

    private function store(string $file, string $path): void
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new RuntimeException('The uploaded list could not be read.');
        }

        $this->files->put($path, $contents);
    }
}
