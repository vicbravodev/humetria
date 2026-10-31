<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\EndReason;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Ends a telework period (numeral 5.1's roster keeps its altas and bajas). Nothing is deleted: the period, its
 * workplaces and what was answered stay as history. The checklists still waiting are cancelled so the portal stops
 * asking for them. An aspirant who never joined can be ended too. `$userId` is null when the system ends it.
 */
final readonly class EndTelework
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(Teleworker $teleworker, EndReason $reason, CarbonImmutable $on, ?string $note, ?int $userId): void
    {
        DB::transaction(function () use ($teleworker, $reason, $on, $note, $userId): void {
            // Locked, so a person ended by hand and by a deactivation at once is ended once.
            $current = Teleworker::query()->lockForUpdate()->findOrFail($teleworker->id);

            if ($current->status === TeleworkerStatus::Ended) {
                throw ValidationException::withMessages(['teleworker' => 'Este periodo de teletrabajo ya terminó.']);
            }

            // Ending on the day it started never ends it before it started.
            $endedAt = $current->started_at !== null && $on->lessThan($current->started_at) ? $current->started_at : $on;
            $current->update(['status' => TeleworkerStatus::Ended, 'ended_at' => $endedAt, 'end_reason' => $reason, 'end_note' => $note]);
            ChecklistApplication::query()
                ->whereNull('submitted_at')->whereNull('cancelled_at')
                ->whereIn('workplace_id', Workplace::query()->where('teleworker_id', $current->id)->select('id'))
                ->update(['cancelled_at' => now()]);

            // Never the note: it may say why a person left.
            $record = ['teleworker_id' => $current->id, 'employee_id' => $current->employee_id, 'work_center_id' => $current->work_center_id, 'reason' => $reason->value];
            $this->audit->handle('telework.teleworker_ended', $current, [...$record, 'ended_by' => $userId]);
            Log::info('telework.teleworker_ended', $record);
        });
    }
}
