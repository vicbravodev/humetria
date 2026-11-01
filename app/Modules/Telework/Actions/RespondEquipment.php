<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Support\OpenedPortal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The person confirms they received an item of equipment, or refuses it justifying the reason (criterio C-22, numeral
 * 10.3 obs. a 5.7). The time and the address stay as evidence, never in a log; the reason neither. Answered once: an
 * item that is not theirs does not exist for them.
 */
final readonly class RespondEquipment
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ModelNotFoundException when the item was not delivered to this person.
     * @throws ValidationException when it was already answered, or a refusal has no reason.
     */
    public function handle(OpenedPortal $portal, string $itemId, bool $received, ?string $reason, ?string $ip): void
    {
        $reason = $received ? null : trim($reason ?? '');

        DB::transaction(function () use ($portal, $itemId, $received, $reason, $ip): void {
            $item = EquipmentItem::query()->where('teleworker_id', $portal->teleworkerId)->lockForUpdate()->findOrFail($itemId);

            if ($item->status !== EquipmentStatus::Pending) {
                throw ValidationException::withMessages(['equipment' => 'Ya respondió sobre este artículo.']);
            }

            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'Escriba el motivo por el que no lo acepta.']);
            }

            $status = $received ? EquipmentStatus::Received : EquipmentStatus::Refused;
            $item->update(['status' => $status, 'refusal_reason' => $reason, 'responded_at' => now(), 'responded_ip' => $ip]);

            $record = ['equipment_id' => $item->id, 'teleworker_id' => $item->teleworker_id, 'status' => $status->value];
            $this->audit->handle('telework.equipment_responded', $item, $record);
            Log::info('telework.equipment_responded', $record);
        });
    }
}
