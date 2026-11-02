<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Assistant\AiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One message to Humi: the tokens it used, the tools it called and how long it took. Never its text.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int|null $participant_id
 * @property string|null $conversation_id
 * @property string $agent
 * @property int $input_tokens
 * @property int $output_tokens
 * @property list<string> $tools
 * @property int $latency_ms
 * @property bool $stopped
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'participant_id', 'conversation_id', 'agent', 'input_tokens', 'output_tokens', 'tools', 'latency_ms', 'stopped', 'created_at'])]
#[UseFactory(AiUsageFactory::class)]
final class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tools' => 'array', 'stopped' => 'boolean', 'created_at' => 'immutable_datetime'];
    }
}
