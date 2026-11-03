<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use App\Modules\Seating\Enums\DocumentKind;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\SeatingDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A PDF RH keeps for the inspection: its kind, its date and the incisos of Quinta it covers. Never deleted (criterio C-17).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property DocumentKind $kind
 * @property string $title
 * @property CarbonImmutable $document_on
 * @property list<string> $incisos
 * @property string $file_path
 * @property int $uploaded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'kind', 'title', 'document_on', 'incisos', 'file_path', 'uploaded_by'])]
#[UseFactory(SeatingDocumentFactory::class)]
final class SeatingDocument extends Model
{
    /** @use HasFactory<SeatingDocumentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => DocumentKind::class, 'document_on' => 'immutable_date', 'incisos' => 'array'];
    }
}
