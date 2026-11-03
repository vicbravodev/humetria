<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Instruments\Contracts\Nom035Policy;

/**
 * What the policy page receives: the draft being written, with what each section allows. The words are the company's own
 * and are meant to be read by its people; nothing here points at a person.
 */
final readonly class PolicyPages
{
    public function __construct(private Nom035Policy $policy, private DocumentTemplates $templates) {}

    /**
     * @return array{draft: array{id: string, sections: list<array<string, mixed>>}|null, note: string, can: array{manage: bool}}
     */
    public function show(bool $canManage): array
    {
        $draft = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Draft)->first();

        return [
            'draft' => $draft === null ? null : ['id' => $draft->id, 'sections' => $this->sections($draft)],
            'note' => $this->policy->template()->note,
            'can' => ['manage' => $canManage],
        ];
    }

    /**
     * @return list<array{key: string, heading: string, text: string, items: list<string>, listStyle: string|null, removable: bool, required: bool}>
     */
    private function sections(Document $draft): array
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
