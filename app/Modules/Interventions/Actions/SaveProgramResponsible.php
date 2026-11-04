<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Actions\RecordAudit;
use App\Modules\Interventions\Models\Program;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR says who answers for the Programa (num. 8.4 f): a member of the company, who is named by their own name and receives
 * the reminders, or a person from outside, by a free name. It is the default of every action. The change goes to the audit
 * trail without the name (a free text may carry data of a person).
 */
final readonly class SaveProgramResponsible
{
    public function __construct(private Members $members, private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     * @throws ModelNotFoundException when the Programa is not of this company.
     */
    public function handle(string $programId, ?int $userId, ?string $name): Program
    {
        $program = Program::query()->findOrFail($programId);
        $member = $userId === null ? null : $this->members->find($userId);
        $free = trim($name ?? '');

        if ($userId !== null && $member === null) {
            throw ValidationException::withMessages(['responsible_user_id' => 'Esa persona no pertenece a la empresa.']);
        }

        if ($member === null && $free === '') {
            throw ValidationException::withMessages(['responsible_name' => 'Indica quién es responsable del Programa.']);
        }

        return DB::transaction(function () use ($program, $member, $free): Program {
            $program->update(['responsible_user_id' => $member?->userId, 'responsible_name' => $member->name ?? $free]);
            Log::info('intervention.program_updated', ['program_id' => $program->id, 'responsible_is_member' => $member !== null]);
            $this->audit->handle('interventions.responsible_changed', $program, ['campaign_id' => $program->campaign_id, 'responsible_is_member' => $member !== null]);

            return $program;
        });
    }
}
