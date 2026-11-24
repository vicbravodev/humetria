<?php

declare(strict_types=1);

// The criteria doc lists the areas of a role by hand (C-05): the fixture of the areas is what the panel offers, so the doc
// cannot name fewer or other areas than it.
it('names in criterion C-05 every area of the roles page', function (): void {
    $areas = json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/permissions/areas.json'), true, flags: JSON_THROW_ON_ERROR);
    $doc = (string) file_get_contents(dirname(__DIR__, 2).'/docs/normas/permisos/criterios.md');
    preg_match('/### C-05\..*?(?=\n### )/s', $doc, $section);
    $text = (string) preg_replace('/\s+/', ' ', $section[0] ?? '');

    expect($areas)->not->toBeEmpty()->and($text)->not->toBe('');

    foreach ($areas as $area) {
        expect($text)->toContain($area['label']);
    }
});
