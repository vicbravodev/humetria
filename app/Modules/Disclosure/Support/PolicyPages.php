<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the policy pages receive: the draft being written, the version published last with its words, the history of
 * versions a page at a time and how many centers are in use. The words are the company's own and are meant to be read by
 * its people; nothing here points at a person.
 */
final readonly class PolicyPages
{
    public function __construct(private Nom035Policy $policy, private DocumentTemplates $templates, private WorkCenters $workCenters, private Companies $companies) {}

    /**
     * @return array<string, mixed>
     */
    public function show(bool $canManage): array
    {
        $draft = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Draft)->first();
        $latest = $this->published()->withCount('centers')->orderByDesc('version')->first();

        return [
            'draft' => $draft === null ? null : ['id' => $draft->id, 'sections' => $this->draftSections($draft)],
            'latest' => $latest === null ? null : $this->version($latest, withWords: true),
            'history' => $this->history(),
            'centersInUse' => count(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)),
            'note' => $this->policy->template()->note,
            'can' => ['manage' => $canManage],
        ];
    }

    /**
     * A published version to read, with its words.
     *
     * @return array<string, mixed>
     *
     * @throws ModelNotFoundException when the version does not exist, is a draft or is another company's.
     */
    public function showVersion(string $documentId): array
    {
        return ['version' => $this->version($this->published()->withCount('centers')->findOrFail($documentId), withWords: true)];
    }

    /**
     * @return Builder<Document>
     */
    private function published(): Builder
    {
        return Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function history(): LengthAwarePaginator
    {
        return $this->published()->withCount('centers')->orderByDesc('version')
            ->paginate(config()->integer('disclosure.history_per_page'), ['id', 'version', 'covers', 'published_at'], 'versions')
            ->withQueryString()
            ->through(fn (Document $document): array => $this->version($document, withWords: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function version(Document $document, bool $withWords): array
    {
        $summary = [
            'id' => $document->id,
            'version' => $document->version,
            'publishedOn' => $document->published_at === null ? null : Days::moment($document->published_at),
            'covers' => $document->covers ?? [],
            'centers' => $document->centers_count ?? 0,
        ];

        return $withWords ? [...$summary, 'sections' => PolicyText::sections($document->content, $this->legalName($document))] : $summary;
    }

    private function legalName(Document $document): string
    {
        $center = DocumentCenter::query()->where('document_id', $document->id)->value('work_center_id');
        $profile = is_string($center) ? $this->workCenters->profile($center) : null;

        return $profile->legalName ?? $this->companies->current()->tradeName;
    }

    /**
     * @return list<array{key: string, heading: string, text: string, items: list<string>, listStyle: string|null, removable: bool, required: bool}>
     */
    private function draftSections(Document $draft): array
    {
        $rules = [];

        foreach ($this->templates->sections($draft->kind) as $section) {
            $rules[$section->key] = $section;
        }

        return array_map(fn (array $section): array => [
            'key' => $section['key'],
            'heading' => $section['heading'],
            'text' => $section['text'],
            'items' => $section['items'],
            'listStyle' => $section['list_style'],
            'removable' => $rules[$section['key']]->removable ?? true,
            'required' => $rules[$section['key']]->required ?? false,
        ], $draft->content);
    }
}
