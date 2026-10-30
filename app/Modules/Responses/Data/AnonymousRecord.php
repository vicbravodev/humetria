<?php

declare(strict_types=1);

namespace App\Modules\Responses\Data;

/**
 * An anonymous Guía II or III as results read it: the area it was answered in, the version of the instrument it was
 * given in, and the answers. Nothing more.
 */
final readonly class AnonymousRecord
{
    /**
     * @param  array<array-key, string>  $answers
     */
    public function __construct(public string $areaId, public string $version, public array $answers) {}
}
