<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * What a tool of Humi answers: compact, stable JSON the model reads and the panel draws as a card. `render` says which
 * card (progress, risk_levels, status_list or list) and `rows` carries its figures; `link` is the screen where the
 * person sees it in full and `note` a sentence for what there is no figure for (and why). Text that came from the
 * company's own data (names of centers or areas) is a plain value here, never an instruction.
 */
final readonly class ToolCard
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public string $render,
        public string $title,
        public array $rows = [],
        public ?string $link = null,
        public ?string $note = null,
        public bool $truncated = false,
        public ?int $minGroup = null,
    ) {}

    public function toJson(): string
    {
        return json_encode(get_object_vars($this), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
