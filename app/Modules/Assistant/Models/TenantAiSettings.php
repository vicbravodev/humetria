<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Assistant\TenantAiSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether Humi is on for a company and its monthly budget of tokens; no budget means the platform's default. A company
 * with no row has Humi on with the default.
 *
 * @property int $id
 * @property string $tenant_id
 * @property bool $enabled
 * @property int|null $monthly_token_budget
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'enabled', 'monthly_token_budget'])]
#[UseFactory(TenantAiSettingsFactory::class)]
final class TenantAiSettings extends Model
{
    /** @use HasFactory<TenantAiSettingsFactory> */
    use BelongsToCurrentTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
