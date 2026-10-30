<?php

declare(strict_types=1);

namespace App\Modules\Results\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Results\ReportShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A temporary link to an evaluation's report. Only the hash of its token is stored; the link itself is shown once.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('result_shares')]
#[Fillable(['tenant_id', 'campaign_id', 'token_hash', 'expires_at', 'revoked_at', 'created_by', 'created_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ReportShareFactory::class)]
final class ReportShare extends Model
{
    /** @use HasFactory<ReportShareFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Links that still open: not revoked, and before the moment they expire.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public static function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
