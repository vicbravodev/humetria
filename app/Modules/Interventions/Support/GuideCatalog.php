<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Interventions\Enums\ItemScope;

/**
 * The places a Programa can attend in a guide: the final score, its categories and its domains, with the names the norm
 * gives them (Tablas 3 and 6). Instruments has no catalog of its own, so it is read from the scoring of one complete
 * questionnaire: the keys and labels do not depend on what was answered. It is what HR picks from to add a row.
 */
final readonly class GuideCatalog
{
    public function __construct(private Instruments $instruments) {}

    /**
     * @return list<array{scope: ItemScope, key: string, label: string, category: string|null}>
     */
    public function groups(string $guide): array
    {
        $definition = $this->instruments->definition($guide);
        $options = array_column($definition->options, 'value');
        $answers = ['serves_clients' => 'yes', 'supervises' => 'yes'];

        foreach ($definition->itemNumbers() as $number) {
            $answers[(string) $number] = $options[0];
        }

        $score = $this->instruments->scoreRisk($guide, $answers, $definition->version);
        $groups = [['scope' => ItemScope::Final, 'key' => 'final', 'label' => ItemScope::Final->label(), 'category' => null]];

        foreach ($score->categories as $category) {
            $groups[] = ['scope' => ItemScope::Category, 'key' => $category->key, 'label' => $category->label, 'category' => null];
        }

        foreach ($score->domains as $domain) {
            $groups[] = ['scope' => ItemScope::Domain, 'key' => $domain->key, 'label' => $domain->label, 'category' => $domain->category];
        }

        return $groups;
    }
}
