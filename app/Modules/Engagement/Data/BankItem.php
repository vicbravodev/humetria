<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Data;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;

/** A question of the bank. A rewrite of the text raises `wording`; the text of a wording is never edited in place. */
final readonly class BankItem
{
    public function __construct(
        public string $key,
        public string $text,
        public ItemScale $scale,
        public ItemBlock $block,
        public ?Driver $driver,
        public bool $anchor = false,
        public bool $candidate = false,
        public int $wording = 1,
    ) {}

    /** What a survey stores for the question: the key and the wording it was asked with. */
    public function ref(): string
    {
        return "{$this->key}@{$this->wording}";
    }
}
