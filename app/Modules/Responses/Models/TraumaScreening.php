<?php

declare(strict_types=1);

namespace App\Modules\Responses\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Responses\TraumaScreeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A person's Guía I, identified on purpose: whoever needs a clinical assessment must be channeled to care (num. 5.5)
 * and their name recorded (num. 5.8 c). Only authorized roles see it. Insert-only.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $campaign_id
 * @property string $employee_id
 * @property string $instrument_version
 * @property array<array-key, string> $answers
 * @property bool $requires_clinical_assessment
 * @property CarbonImmutable $answered_at
 */
#[Fillable(['tenant_id', 'campaign_id', 'employee_id', 'instrument_version', 'answers', 'requires_clinical_assessment', 'answered_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[WithoutTimestamps]
#[UseFactory(TraumaScreeningFactory::class)]
final class TraumaScreening extends Model
{
    /** @use HasFactory<TraumaScreeningFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'requires_clinical_assessment' => 'boolean',
            'answered_at' => 'immutable_datetime',
        ];
    }
}
