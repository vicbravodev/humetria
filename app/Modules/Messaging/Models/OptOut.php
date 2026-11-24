<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Messaging\OptOutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Someone who asked not to be written to: the HMAC of their phone for one company, or for all (no tenant_id).
 * Not deleted by the retention: it is what stops us writing to them again.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $recipient_hash
 * @property int $key_version
 * @property string $source
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'recipient_hash', 'key_version', 'source'])]
#[UseFactory(OptOutFactory::class)]
final class OptOut extends Model
{
    /** @use HasFactory<OptOutFactory> */
    use HasFactory, HasVersion4Uuids;

    public const ?string UPDATED_AT = null;

    protected $table = 'messaging_opt_outs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
