<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\DocumentKind;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the documents page sends to the browser: the documents of a center, a page at a time, newest first.
 */
final readonly class DocumentPages
{
    private const int PER_PAGE = 15;

    /**
     * @return array<string, mixed>
     */
    public function index(mixed $tenant, WorkCenterSummary $center): array
    {
        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'documents' => $this->documents($tenant, $center->id),
            'canManage' => Gate::allows('manage-seating'),
            'kinds' => array_map(fn (DocumentKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], DocumentKind::cases()),
            'incisos' => array_map(fn (string $letter, string $label): array => ['value' => $letter, 'label' => "{$letter}. {$label}"], array_keys(QuintaIncisos::LABELS), array_values(QuintaIncisos::LABELS)),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, kindLabel: string, title: string, documentOn: string, incisos: list<string>, fileHref: string}>
     */
    private function documents(mixed $tenant, string $workCenterId): LengthAwarePaginator
    {
        $page = SeatingDocument::query()->where('work_center_id', $workCenterId)->orderByDesc('document_on')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return $page->through(fn (SeatingDocument $document): array => [
            'id' => $document->id,
            'kindLabel' => $document->kind->label(),
            'title' => $document->title,
            'documentOn' => $document->document_on->toDateString(),
            'incisos' => $document->incisos,
            'fileHref' => route('tenant.seating.documents.file', [$tenant, $document->id]),
        ]);
    }
}
