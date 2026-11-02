<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Assistant\ConversationParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * A person in one company, as the SDK knows who owns a conversation with Humi. One per company and user.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'user_id'])]
#[UseFactory(ConversationParticipantFactory::class)]
final class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use BelongsToCurrentTenant, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Conversation, $this>
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'participant');
    }

    /**
     * @return MorphMany<ConversationMessage, $this>
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(ConversationMessage::class, 'participant');
    }
}
