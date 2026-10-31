<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\EquipmentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\Teleworker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RH registers an item of equipment delivered to a person (numerals 5.7 and 5.1 k, criterio C-22). It waits for the
 * person's receipt in their portal. There is no edit or delete: a registration is evidence, and the person answers it.
 */
final readonly class RegisterEquipment
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(Teleworker $teleworker, EquipmentKind $kind, string $description, CarbonImmutable $deliveredOn, int $userId): EquipmentItem
    {
        return DB::transaction(function () use ($teleworker, $kind, $description, $deliveredOn, $userId): EquipmentItem {
            $item = EquipmentItem::query()->create([
                'tenant_id' => $teleworker->tenant_id,
                'teleworker_id' => $teleworker->id,
                'kind' => $kind,
                'description' => trim($description),
                'delivered_on' => $deliveredOn,
                'status' => EquipmentStatus::Pending,
                'registered_by' => $userId,
            ]);

            // Never the description: it is free text about what the person was given.
            $record = ['equipment_id' => $item->id, 'teleworker_id' => $teleworker->id, 'kind' => $kind->value];
            $this->audit->handle('telework.equipment_registered', $item, $record);
            Log::info('telework.equipment_registered', $record);

            return $item;
        });
    }
}
