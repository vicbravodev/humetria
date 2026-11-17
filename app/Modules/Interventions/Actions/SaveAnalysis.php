<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Actions\RecordAudit;
use App\Modules\Interventions\Models\ProgramItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * HR writes the analysis of a row: the "análisis de cada categoría y dominio" that Tablas 4 and 7 ask for at Alto and Muy alto
 * (II.4, III.4). A blank text clears it. What HR wrote never reaches the log nor the audit trail: only whether it is written.
 */
final readonly class SaveAnalysis
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ModelNotFoundException when the row is not of this company.
     */
    public function handle(string $itemId, ?string $analysis): ProgramItem
    {
        $item = ProgramItem::query()->findOrFail($itemId);
        $text = trim($analysis ?? '');

        $item->update(['analysis' => $text === '' ? null : $text]);
        Log::info('intervention.analysis_saved', ['item_id' => $item->id, 'program_id' => $item->program_id, 'written' => $text !== '']);
        $this->audit->handle('interventions.analysis_saved', $item, ['item_id' => $item->id, 'program_id' => $item->program_id, 'written' => $text !== '']);

        return $item;
    }
}
