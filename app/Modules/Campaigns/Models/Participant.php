<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Modules\Campaigns\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Campaigns\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person invited to a campaign: the guides they were given, and the hash of their one-time link. Only the hash is
 * stored; the link itself exists only in the email.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $employee_id
 * @property bool $in_sample
 * @property string|null $token_hash
 * @property CarbonImmutable|null $invited_at
 * @property InvitationStatus|null $invitation_status
 * @property string|null $invitation_batch
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('campaign_participants')]
#[Fillable(['tenant_id', 'campaign_id', 'employee_id', 'in_sample', 'token_hash', 'invited_at', 'invitation_status', 'invitation_batch'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ParticipantFactory::class)]
final class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<ParticipantInstrument, $this>
     */
    public function instruments(): HasMany
    {
        return $this->hasMany(ParticipantInstrument::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'in_sample' => 'boolean',
            'invited_at' => 'immutable_datetime',
            'invitation_status' => InvitationStatus::class,
        ];
    }
}
