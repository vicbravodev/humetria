<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/** One question of a document written with questions; its answers make the section with the same key. */
final readonly class Question
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(public string $key, public string $topic, public string $title, public array $fields, public ?string $hint = null) {}
}
