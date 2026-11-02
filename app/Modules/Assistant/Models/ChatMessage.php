<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message of a conversation with Humi, as the SDK stores it, read here with typed columns. Only deleted here.
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $participant_type
 * @property int $participant_id
 */
final class ChatMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return config()->string('ai.conversations.tables.messages', 'agent_conversation_messages');
    }

    public function getConnectionName(): ?string
    {
        $connection = config('ai.conversations.connection');

        return is_string($connection) ? $connection : null;
    }
}
