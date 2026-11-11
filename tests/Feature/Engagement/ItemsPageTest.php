<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

function climateItemsUrl(Tenant $tenant, Survey $survey): string
{
    return route('tenant.climate.results.items', [$tenant, $survey]);
}

it('lists every question by theme with its favorable share, its five-step distribution and how many answered', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6, fn (int $i): array => ['eng.pride' => 5, 'clarity.goals' => $i < 3 ? 4 : 2]);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $survey))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/items')
            ->where('available', true)
            ->where('sections.0.key', 'outcome')
            ->where('sections.0.items.0.key', 'eng.pride')
            ->where('sections.0.items.0.favorable', 100)
            ->where('sections.0.items.0.answered', 6)
            ->where('sections.0.items.0.distribution', [0, 0, 0, 0, 100])
            ->where('sections.1.key', 'clarity')
            ->where('sections.1.items.0.key', 'clarity.goals')
            ->where('sections.1.items.0.favorable', 50)
            ->where('sections.1.items.0.unfavorable', 50)
            ->where('sections.1.items.0.delta', null)
            ->where('sections.1.score', null));
});

it('says a question has too few answers instead of a number when under five answered it', function (): void {
    $survey = climateResultsSurvey($this->tenant, 8, fn (int $i): array => ['eng.pride' => 4, 'clarity.goals' => $i < 4 ? 5 : null]);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.1.items.0.shown', false)
            ->where('sections.1.items.0.answered', null)
            ->where('sections.1.items.0.favorable', null)
            ->where('sections.1.items.0.distribution', null));
});

it('reports the themes of two questions question by question, with no score of their own', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.4.key', 'recognition')
            ->where('sections.4.score', null)
            ->where('sections.4.note', 'Se reporta pregunta por pregunta.')
            ->has('sections.4.items', 2)
            ->where('sections.1.note', null));
});

it('has no loop question in the first wave and has it, compared with nothing, when it was asked', function (): void {
    $first = climateResultsSurvey($this->tenant, 6);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $first))
        ->assertInertia(fn (Assert $page) => $page->where('sections', fn ($sections) => collect($sections)->doesntContain('key', 'loop')));

    $first->update(['items' => [...$first->items, 'loop.action@1']]);
    $this->get(climateItemsUrl($this->tenant, $first))
        ->assertInertia(fn (Assert $page) => $page->where('sections', fn ($sections) => collect($sections)->contains('key', 'loop')));
});

it('compares each question with the same wording of the previous survey', function (): void {
    $before = climateResultsSurvey($this->tenant, 30, fn (int $i): array => ['clarity.goals' => $i < 15 ? 4 : 2]);
    $before->update(['closed_at' => '2026-01-10 12:00:00']);
    $now = climateResultsSurvey($this->tenant, 30, fn (int $i): array => ['clarity.goals' => 4]);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $now))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.0.items.0.delta', null)
            ->where('sections.1.items.0.delta.points', 50)
            ->where('sections.1.items.0.delta.direction', 'up'));
});

it('waits for the close and does not find the survey of another company', function (): void {
    $open = climateResultsSurvey($this->tenant, 6, closed: false);
    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $open))
        ->assertInertia(fn (Assert $page) => $page->where('available', false)->where('sections', []));

    $other = Tenant::factory()->create();
    $foreign = climateResultsSurvey($other, 6);
    workIn($this->tenant);

    $this->get(climateItemsUrl($this->tenant, $foreign))->assertNotFound();
});

it('leaves the manager questions out and puts no individual response in the props', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6, fn (int $i): array => ['manager.respect' => 5, 'clarity.goals' => 4]);

    $this->actingAs($this->hr)->get(climateItemsUrl($this->tenant, $survey))
        ->assertInertia(function (Assert $page): void {
            expectNoEngagementResponses($page->toArray()['props']);
            expect(json_encode($page->toArray()['props']))->not->toContain('manager.');
        });
});
