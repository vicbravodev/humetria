<?php

declare(strict_types=1);

namespace App\Modules\Sso\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sso\SsoDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An email domain a company claims, and the token its DNS must publish to prove it. No per-company scope, like the connection.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $domain
 * @property string $token
 * @property CarbonImmutable|null $verified_at
 */
#[Fillable(['tenant_id', 'domain', 'token', 'verified_at'])]
#[UseFactory(SsoDomainFactory::class)]
final class SsoDomain extends Model
{
    /** @use HasFactory<SsoDomainFactory> */
    use HasFactory, HasVersion4Uuids;

    /** The DNS name that must carry the TXT record. */
    public function recordName(): string
    {
        return "_humetria-sso.{$this->domain}";
    }

    /** What that TXT record must say. */
    public function recordValue(): string
    {
        return "humetria-sso-verification={$this->token}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }
}
