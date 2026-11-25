<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal note of Humetría's staff about a company. Written and read only from the console, where there is no current
 * company, so it has a tenant_id but no per-company scope; the company never sees it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int|null $author_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'author_id', 'body'])]
#[UseFactory(TenantNoteFactory::class)]
final class TenantNote extends Model
{
    /** @use HasFactory<TenantNoteFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
