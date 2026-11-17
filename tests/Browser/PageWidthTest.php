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
    // A page that fills the frame keeps its running text readable: no paragraph of more than a line runs past 48 em
    // (≈ 620 px of body text, the `max-w` of running text; a lead in a bigger size may run a little wider).
    $longLines = '() => [...document.querySelectorAll(\'[data-slot="page-body"] p\')].filter((p) => p.textContent.length > 120 && p.getBoundingClientRect().width / parseFloat(getComputedStyle(p).fontSize) > 48).map((p) => p.textContent.slice(0, 40)).join(" | ")';
    $edges = [];

    foreach (['employees', 'attendance', 'attendance/policy', 'vacations', 'complaints', 'complaints/channel', 'compensation', 'disclosure', 'telework', 'seating', 'access', 'telework/settings'] as $path) {
        $page = visit("{$url}/{$path}")->resize($width, 900)->assertNoJavaScriptErrors();
        $edges[$path] = $page->script($edge);
        expect($page->script($scrolls))->toBeFalse("{$path} scrolls sideways at {$width} px")
            ->and($page->script($longLines))->toBe('', "{$path} runs its text too long at {$width} px");
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
