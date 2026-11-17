<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

/*
 * One width rule for every page (docs/design-system/design-system.md; tests/Unit/PageWidthTest.php keeps the classes):
 * the frame of the app sets the width, so moving between modules the content starts at the same column, and no page
 * scrolls sideways. Employees never had a cap of its own: it is the column the others must start at.
 */

it('starts the content of every module at the same column, with no sideways scroll', function (int $width): void {
    $url = signInToPanel();
    $edge = <<<'JS'
        () => {
            const title = document.querySelector('[data-slot="page-body"] h1');
            return title === null ? null : Math.round(title.getBoundingClientRect().left);
        }
        JS;
    $scrolls = '() => document.documentElement.scrollWidth > document.documentElement.clientWidth';
    $edges = [];

    foreach (['employees', 'attendance', 'vacations', 'complaints', 'compensation', 'disclosure', 'telework', 'seating', 'telework/settings'] as $path) {
        $page = visit("{$url}/{$path}")->resize($width, 900)->assertNoJavaScriptErrors();
        $edges[$path] = $page->script($edge);
        expect($page->script($scrolls))->toBeFalse("{$path} scrolls sideways at {$width} px");
    }

    expect(array_unique($edges))->toHaveCount(1, json_encode($edges, JSON_THROW_ON_ERROR));
})->with([1440, 1920]);

it('lines the NOM-035 report and its bar up with the column of every page, at the width of its sheet', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::factory()->for($tenant)->create()->id]);
    $title = '() => Math.round(document.querySelector(\'[data-slot="page-body"] h1\').getBoundingClientRect().left)';
    // The bar's row and the report's column: both 1120 px wide, both starting where the page does.
    $sheets = '() => JSON.stringify([...document.querySelectorAll(\'[class*="max-w-[1120px]"]\')].map((sheet) => { const box = sheet.getBoundingClientRect(); return [Math.round(box.left), Math.round(box.width)]; }))';

    $column = visit("{$url}/employees")->resize(1920, 900)->script($title);
    $page = visit("{$url}/campaigns/{$campaign->id}/report")->resize(1920, 900)->assertNoJavaScriptErrors();
    $frame = visit("{$url}/employees")->resize(1920, 900)->script('() => Math.round(document.querySelector(\'[data-slot="page-body"]\').getBoundingClientRect().left)');

    expect($page->script($title))->toBe($column)
        ->and($page->script($sheets))->toBe(json_encode([[$frame, 1120], [$frame, 1120]], JSON_THROW_ON_ERROR));
});
