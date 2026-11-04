<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\DocumentCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A work center a published version reached. Written once at publication and never changed (a trigger).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string $work_center_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('disclosure_document_centers')]
#[Fillable(['tenant_id', 'document_id', 'work_center_id'])]
#[UseFactory(DocumentCenterFactory::class)]
final class DocumentCenter extends Model
{
    /** @use HasFactory<DocumentCenterFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;
}
