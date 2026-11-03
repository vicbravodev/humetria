<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A text the company tells its people: a draft, or a version published for some work centers (criterio C-50). A published
 * version is evidence (num. 10.4) and is never changed. `content` is plain text only: sections of paragraphs and items.
 *
 * @property string $id
 * @property string $tenant_id
 * @property DocumentKind $kind
 * @property int|null $version
 * @property DocumentStatus $status
 * @property list<array{key: string, heading: string, text: string, items: list<string>, list_style: string|null}> $content
 * @property string|null $template_version
 * @property list<string>|null $covers
 * @property int|null $created_by
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read int|null $centers_count
 */
#[Table('disclosure_documents')]
#[Fillable(['tenant_id', 'kind', 'version', 'status', 'content', 'template_version', 'covers', 'created_by', 'published_at', 'published_by'])]
#[UseFactory(DocumentFactory::class)]
final class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<DocumentCenter, $this>
     */
    public function centers(): HasMany
    {
        return $this->hasMany(DocumentCenter::class, 'document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'status' => DocumentStatus::class,
            'version' => 'integer',
            'content' => 'array',
            'covers' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
