<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * The example policy of Guía IV: its sections in order and the guide's note, which is help for the writer and not part of
 * the policy.
 */
final readonly class PolicyTemplate
{
    /**
     * @param  list<PolicySection>  $sections
     */
    public function __construct(public string $version, public array $sections, public string $note) {}
}
