<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Instruments\Contracts\Nom035Policy;
use App\Modules\Instruments\Data\PolicySection;

/**
 * The sections each kind of text starts from and may keep: the server owns the keys, headings and list styles, HR owns the
 * words. The policy starts from the example of Guía IV; the blocks of 5.7 start from a base text of their own.
 */
final readonly class DocumentTemplates
{
    public function __construct(private Nom035Policy $policy) {}

    /**
     * @return list<PolicySection>
     */
    public function sections(DocumentKind $kind): array
    {
        return match ($kind) {
            DocumentKind::Policy => $this->policy->template()->sections,
            default => [],
        };
    }

    /**
     * The sections as a draft stores them.
     *
     * @return list<array{key: string, heading: string, text: string, items: list<string>, list_style: string|null}>
     */
    public function content(DocumentKind $kind): array
    {
        return array_map(fn (PolicySection $section): array => [
            'key' => $section->key,
            'heading' => $section->heading,
            'text' => $section->text,
            'items' => $section->items,
            'list_style' => $section->listStyle,
        ], $this->sections($kind));
    }
}
