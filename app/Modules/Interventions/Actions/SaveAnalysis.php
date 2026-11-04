<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Interventions\Models\ProgramItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * HR writes the analysis of a row: the "análisis de cada categoría y dominio" that Tablas 4 and 7 ask for at Alto and Muy alto
 * (II.4, III.4). A blank text clears it. What HR wrote never reaches the log.
 */
final readonly class SaveAnalysis
{
    /**
     * @throws ModelNotFoundException when the row is not of this company.
     */
    public function handle(string $itemId, ?string $analysis): ProgramItem
    {
        $item = ProgramItem::query()->findOrFail($itemId);
        $text = trim($analysis ?? '');

        $item->update(['analysis' => $text === '' ? null : $text]);
        Log::info('intervention.analysis_saved', ['item_id' => $item->id, 'program_id' => $item->program_id, 'written' => $text !== '']);

        return $item;
    }
}
