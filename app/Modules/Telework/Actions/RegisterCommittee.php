<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Instruments\Contracts\Nom037Checklist;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\QuestionOrigin;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Registers the Comisión of a work center (criterio C-17) and, with it, the first draft of the center's checklist: the
 * base list copied in order (C-07). Both exist or neither does. A center has one committee.
 */
final readonly class RegisterCommittee
{
    public function __construct(private CurrentTenant $tenant, private Nom037Checklist $base, private RecordAudit $audit) {}

    /**
     * @param  string|null  $actFile  Path of the uploaded PDF, already validated; null when there is none.
     * @param  list<int>  $memberIds  Users of the company that already hold a reviewing role; the caller checks it.
     *
     * @throws ValidationException when the center already has its committee.
     */
    public function handle(string $workCenterId, CarbonImmutable $constitutedOn, ?string $actFile, array $memberIds, int $userId): SafetyCommittee
    {
        $tenantId = $this->tenant->id();
        $path = $actFile === null ? null : "committees/{$tenantId}/{$workCenterId}/act-".Str::uuid().'.pdf';

        try {
            return DB::transaction(function () use ($tenantId, $workCenterId, $constitutedOn, $actFile, $path, $memberIds, $userId): SafetyCommittee {
                try {
                    $committee = SafetyCommittee::query()->create([
                        'tenant_id' => $tenantId,
                        'work_center_id' => $workCenterId,
                        'constituted_on' => $constitutedOn,
                        'act_path' => $path,
                        'created_by' => $userId,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['work_center' => 'Este centro ya tiene registrada su Comisión.']);
                }

                foreach ($memberIds as $memberId) {
                    CommitteeAssignment::query()->create(['tenant_id' => $tenantId, 'work_center_id' => $workCenterId, 'user_id' => $memberId]);
                }

                $this->seedDraft($tenantId, $workCenterId);

                // Last, so a failure above leaves no file behind. $path is set exactly when there is a file: the second check
                // only narrows the type.
                if ($actFile !== null && $path !== null) { // @pest-mutate-ignore: BooleanAndToBooleanOr
                    $this->store($actFile, $path);
                }

                $record = ['work_center_id' => $workCenterId, 'member_ids' => $memberIds];
                $this->audit->handle('telework.committee_registered', $committee, $record);
                Log::info('telework.committee_registered', $record);

                return $committee;
            });
        } catch (Throwable $failure) {
            // The file went in before the commit: a failed registration must not leave it behind.
            if ($path !== null) {
                Storage::disk(config()->string('filesystems.telework_files'))->delete($path);
            }

            throw $failure;
        }
    }

    private function store(string $file, string $path): void
    {
        $stream = fopen($file, 'rb');

        // Laravel turns the warning of a failed fopen() into an ErrorException first; PHP closes the stream at the end of the
        // request even without fclose().
        if ($stream === false || ! Storage::disk(config()->string('filesystems.telework_files'))->put($path, $stream)) { // @pest-mutate-ignore: FalseToTrue
            throw new RuntimeException('The committee act could not be stored.');
        }

        fclose($stream); // @pest-mutate-ignore: RemoveFunctionCall
    }

    /** Draft version 1: the base questions in order; the DOF ones keep their origin, Humetría's are marked as such. */
    private function seedDraft(string $tenantId, string $workCenterId): void
    {
        $checklist = Checklist::query()->create([
            'tenant_id' => $tenantId,
            'work_center_id' => $workCenterId,
            'version' => 1,
            'status' => ChecklistStatus::Draft,
        ]);
        $now = now();
        $rows = [];

        foreach ($this->base->items() as $index => $item) {
            $rows[] = [
                // The query builder turns the uuid into its string itself: the cast only narrows the type.
                'id' => (string) Str::uuid(), // @pest-mutate-ignore: RemoveStringCast
                'tenant_id' => $tenantId,
                'checklist_id' => $checklist->id,
                'section' => $item->section,
                'position' => $index + 1,
                'code' => $item->code,
                'heading' => $item->heading,
                'text' => $item->text,
                'origin' => ($item->fromDof ? QuestionOrigin::Dof : QuestionOrigin::Humetria)->value,
                'favorable' => $item->favorable->value,
                'requirement' => $item->requirement?->value,
                'sensitive' => $item->sensitive,
                'base_code' => $item->code,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('checklist_questions')->insert($rows);
    }
}
