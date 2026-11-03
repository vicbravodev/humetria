<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\GuideCatalog;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * HR adds a row the results did not ask for: a preventive action on a category or domain (num. 8.1 and 8.2), or, in a sample
 * under the minimum, whatever it wants to attend. The row comes from the catalog of the guide of the evaluation, never
 * typed, keeps its label as it is today and has no level or figures: it does not count toward what the Tabla asks.
 */
final readonly class AddManualItem
{
    public function __construct(private CurrentTenant $tenant, private CampaignRecords $campaigns, private GuideCatalog $catalog) {}

    /**
     * @throws ValidationException when it is not in the catalog or already is a row.
     * @throws ModelNotFoundException when the Programa is not of this company.
     */
    public function handle(string $programId, ItemScope $scope, string $key): ProgramItem
    {
        $program = Program::query()->findOrFail($programId);
        $campaign = $this->campaigns->find($program->campaign_id) ?? throw new ModelNotFoundException;
        $guide = array_find($campaign->instruments, fn (string $instrument): bool => $instrument !== 'nom035.guide-i') ?? throw new ModelNotFoundException;
        $group = array_find($this->catalog->groups($guide), fn (array $group): bool => $group['scope'] === $scope && $group['key'] === $key);

        if ($group === null) {
            throw ValidationException::withMessages(['scope_key' => 'Elige una categoría o dominio de la lista.']);
        }

        if (ProgramItem::query()->where('program_id', $program->id)->where('scope', $scope)->where('scope_key', $key)->exists()) {
            throw ValidationException::withMessages(['scope_key' => 'Esa fila ya está en el Programa.']);
        }

        $item = ProgramItem::query()->create([
            'tenant_id' => $this->tenant->id(),
            'program_id' => $program->id,
            'scope' => $scope,
            'scope_key' => $key,
            'label' => $group['label'],
            'category_key' => $group['category'],
            'origin' => ItemOrigin::Manual,
        ]);
        Log::info('intervention.item_added', ['item_id' => $item->id, 'program_id' => $program->id, 'scope' => $scope->value]);

        return $item;
    }
}
