<?php

declare(strict_types=1);

namespace App\Modules\Responses\Models;

use App\Concerns\BelongsToCurrentTenant;
use Database\Factories\Responses\AnonymousResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Guía II or III answer, with no way back to the person who gave it: no employee, participant or token, and no
 * timestamps, since when it was answered would tell who answered it (criterio C-22). Insert-only: a trigger rejects updates and deletes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $work_center_id
 * @property string $area_id
 * @property string $instrument
 * @property string $instrument_version
 * @property array<array-key, string> $answers
 */
#[Fillable(['tenant_id', 'campaign_id', 'work_center_id', 'area_id', 'instrument', 'instrument_version', 'answers'])] // @pest-mutate-ignore: RemoveArrayItem
#[WithoutTimestamps]
#[UseFactory(AnonymousResponseFactory::class)]
final class AnonymousResponse extends Model
{
    /** @use HasFactory<AnonymousResponseFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answers' => 'array'];
    }
}
