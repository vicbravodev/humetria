<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Support\ChecklistOrder;
use App\Modules\Telework\Support\Coverage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the draft as the version it already carries (C-07). Requirements left uncovered are only reported: the
 * Comisión may publish anyway (C-08), and the audit entry records how many were missing.
 */
final readonly class PublishChecklist
{
    public function __construct(private Coverage $coverage, private RecordAudit $audit) {}

    /**
     * @return array{checklist: Checklist, missing: array<int, list<Requirement>>} The published version and the gaps the audit recorded.
     *
     * @throws ValidationException when the checklist is already published.
     */
    public function handle(string $checklistId, int $userId): array
    {
        return ChecklistOrder::editing($checklistId, function (Checklist $checklist) use ($userId): array {
            $checklist->update(['status' => ChecklistStatus::Published, 'published_at' => now(), 'published_by' => $userId]);
            $missing = $this->coverage->missing($checklist);
            $record = [
                'work_center_id' => $checklist->work_center_id,
                'checklist_id' => $checklist->id,
                'version' => $checklist->version,
                'missing_section_1' => count($missing[1]),
                'missing_section_2' => count($missing[2]),
            ];
            $this->audit->handle('telework.checklist_published', $checklist, $record);
            Log::info('telework.checklist_published', $record);

            return ['checklist' => $checklist, 'missing' => $missing];
        });
    }
}
