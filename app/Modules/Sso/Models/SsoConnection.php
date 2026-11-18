<?php

declare(strict_types=1);

namespace App\Modules\Sso\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Sso\SsoConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The OpenID Connect provider of a company. It has a tenant_id but no per-company scope: the sign-in routes read it before any
 * company is resolved, so every query names its company.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $issuer
 * @property string $client_id
 * @property string $client_secret
 * @property bool $enabled
 * @property bool $enforced
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'issuer', 'client_id', 'client_secret', 'enabled', 'enforced'])]
#[Hidden(['client_secret'])]
#[UseFactory(SsoConnectionFactory::class)]
final class SsoConnection extends Model
{
    /** @use HasFactory<SsoConnectionFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['client_secret' => 'encrypted', 'enabled' => 'boolean', 'enforced' => 'boolean'];
    }
}
