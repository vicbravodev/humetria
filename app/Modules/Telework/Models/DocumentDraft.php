<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\DocumentKind;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\DocumentDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A NOM-037 document being written with questions (decisión A3): the answers by field, the paragraphs RH rewrote by section
 * and the question it was on (the number of questions means it reached the review). One per company and kind.
 *
 * @property string $id
 * @property string $tenant_id
 * @property DocumentKind $kind
 * @property array<string, string|list<string>> $answers
 * @property array<string, string> $edits
 * @property int $step
 * @property int $updated_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('telework_document_drafts')]
#[Fillable(['tenant_id', 'kind', 'answers', 'edits', 'step', 'updated_by'])]
#[UseFactory(DocumentDraftFactory::class)]
final class DocumentDraft extends Model
{
    /** @use HasFactory<DocumentDraftFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => DocumentKind::class, 'answers' => 'array', 'edits' => 'array', 'step' => 'integer'];
    }
}
