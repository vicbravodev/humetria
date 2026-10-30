<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Campaigns\ParticipantInstrumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One guide given to a participant, and whether they answered it: the "ya contestó" of the invitation, never what.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $participant_id
 * @property string $instrument
 * @property InstrumentStatus $status
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'participant_id', 'instrument', 'status', 'completed_at', 'cancelled_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ParticipantInstrumentFactory::class)]
final class ParticipantInstrument extends Model
{
    /** @use HasFactory<ParticipantInstrumentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InstrumentStatus::class,
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
