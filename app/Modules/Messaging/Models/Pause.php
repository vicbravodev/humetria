<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A stop of the whole WhatsApp feature, for every company. Open while `ended_at` is null; at most one is open.
 *
 * @property string $id
 * @property string $reason
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 */
#[Fillable(['reason', 'started_at', 'ended_at'])]
final class Pause extends Model
{
    use HasVersion4Uuids;

    public $timestamps = false;

    protected $table = 'messaging_pauses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }
}
