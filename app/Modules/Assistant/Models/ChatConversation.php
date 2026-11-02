<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A conversation with Humi, as the SDK stores it (its own table, named in config/ai.php), read here with typed columns.
 * The row has no company: it belongs to a participant, and the participant to a company. Only read and deleted here; the
 * SDK writes it.
 *
 * @property string $id
 * @property string $participant_type
 * @property int $participant_id
 * @property string $title
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class ChatConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return config()->string('ai.conversations.tables.conversations', 'agent_conversations');
    }

    public function getConnectionName(): ?string
    {
        $connection = config('ai.conversations.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
