<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\DocumentKind;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One version of a NOM-037 document of a work center, with the items of the norm it covers: a PDF the company uploaded
 * (`path`, which several centers may share) or a text written with questions (`body`, its sections, with the version of
 * the template it was written with). Never updated or deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property DocumentKind $kind
 * @property int $version
 * @property string|null $path
 * @property list<array{key: string, heading: string, text: string}>|null $body
 * @property string|null $template_version
 * @property list<string> $covered_items
 * @property int $uploaded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'kind', 'version', 'path', 'body', 'template_version', 'covered_items', 'uploaded_by'])]
#[UseFactory(TeleworkDocumentFactory::class)]
final class TeleworkDocument extends Model
{
    /** @use HasFactory<TeleworkDocumentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => DocumentKind::class, 'covered_items' => 'array', 'body' => 'array'];
    }

    /**
     * Only the version in force: the latest of each kind in each work center.
     *
     * @param  Builder<TeleworkDocument>  $query
     */
    #[Scope]
    protected function inForce(Builder $query): void
    {
        $query->where('version', self::query()->withoutGlobalScopes()->from('telework_documents as newer')
            ->selectRaw('max(newer.version)')
            ->whereColumn('newer.work_center_id', 'telework_documents.work_center_id')
            ->whereColumn('newer.kind', 'telework_documents.kind'));
    }
}
