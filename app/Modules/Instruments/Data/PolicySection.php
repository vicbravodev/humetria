<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A section of a policy, in plain text only: paragraphs split by a blank line in `text` and one line per item. `listStyle`
 * says how the items are marked ("numbers" or "letters"); `required` means HR must fill it in to publish, and a section that
 * is not `removable` always stays. `{legal_name}` is the only placeholder: the company's legal name, put in on display.
 */
final readonly class PolicySection
{
    /**
     * @param  list<string>  $items
     * @param  'numbers'|'letters'|null  $listStyle
     */
    public function __construct(
        public string $key,
        public string $heading,
        public string $text,
        public array $items = [],
        public ?string $listStyle = null,
        public bool $removable = true,
        public bool $required = false,
    ) {}
}
