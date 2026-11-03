<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\ResultPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A closed evaluation RH published to the people of its center (num. 7.8; criterio C-54), until it withdraws it. It holds the
 * evaluation, the center and who acted, and no column about a person, an invitation or a token.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $work_center_id
 * @property CarbonImmutable $published_at
 * @property int $published_by
 * @property CarbonImmutable|null $withdrawn_at
 * @property int|null $withdrawn_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('disclosure_result_publications')]
#[Fillable(['tenant_id', 'campaign_id', 'work_center_id', 'published_at', 'published_by', 'withdrawn_at', 'withdrawn_by'])]
#[UseFactory(ResultPublicationFactory::class)]
final class ResultPublication extends Model
{
    /** @use HasFactory<ResultPublicationFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime', 'withdrawn_at' => 'immutable_datetime'];
    }
}
