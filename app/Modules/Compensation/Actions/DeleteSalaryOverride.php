<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Actions;

use App\Actions\RecordAudit;
use App\Modules\Compensation\Models\SalaryOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DeleteSalaryOverride
{
    public function __construct(private RecordAudit $audit) {}

    /** Takes out an individual salary that was typed by mistake: the person's earlier one is in force again, or the tabulator's if none. */
    public function handle(SalaryOverride $override): void
    {
        DB::transaction(function () use ($override): void {
            $override->delete();
            // A count only: who earns what is not for a log or the audit trail.
            $this->audit->handle('compensation.override_deleted', null, ['overrides' => 1]);
        });

        Log::info('compensation.override_deleted', ['override_id' => $override->id]);
    }
}
