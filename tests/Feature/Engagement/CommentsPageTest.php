<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Jobs\SummarizeComments;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
    $this->survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
});

function commentsUrl(Tenant $tenant, Survey $survey, string $query = ''): string
{
    return route('tenant.climate.comments', [$tenant, $survey]).$query;
}

/** `$count` comments of the company on a question. */
function companyComments(Survey $survey, string $item, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Comment::factory()->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id, 'item_key' => $item, 'body' => "Sobre {$item} número {$i}"]);
    }
}

it('gives the viewer the summary of the company and not one original comment', function (): void {
    companyComments($this->survey, 'open.best', 12);
    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(commentsUrl($this->tenant, $this->survey))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->component('engagement/comments')
                ->where('available', true)
                ->where('item', 'open.best')
                ->where('commentsState', 'hidden')
                ->where('comments', null)
                ->where('summary.status', 'ready')
                ->where('summary.themes.valued.0.theme', 'Buen trato entre compañeros')
                ->where('retryId', null);
            expectNoEngagementResponses($page->toArray()['props']);
        });
});

it('gives who manages climate the originals of a question from ten, paginated, and nothing else of each', function (int $count, string $state): void {
    companyComments($this->survey, 'open.best', $count);
    companyComments($this->survey, 'open.improve', 12);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $this->survey, '?item=open.best'))
        ->assertInertia(function (Assert $page) use ($count, $state): void {
            $props = $page->toArray()['props'];
            expect($props['commentsState'])->toBe($state);

            if ($state === 'shown') {
                expect($props['comments']['total'])->toBe($count)
                    ->and($props['comments']['data'])->toHaveCount(10)
                    ->and(array_keys($props['comments']['data'][0]))->toBe(['body']);
            } else {
                // Not a comment and not how many there are.
                expect($props['comments'])->toBeNull()->and(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('Sobre open.best');
            }
        });
})->with([[9, 'few'], [10, 'shown'], [0, 'few']]);

it('applies the minimum to each question, and never mixes the comments of a manager in', function (): void {
    companyComments($this->survey, 'open.best', 4);
    companyComments($this->survey, 'open.improve', 10);
    foreach (range(1, 12) as $i) {
        Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'item_key' => 'manager.keep', 'comment_group_id' => '0d3f1b6c-5d5e-4b0e-8a3d-2f7a9c1d4e10', 'body' => "De un jefe {$i}"]);
    }

    $this->actingAs($this->hr);
    $this->get(commentsUrl($this->tenant, $this->survey, '?item=open.best'))->assertInertia(fn (Assert $page) => $page->where('commentsState', 'few'));
    $this->get(commentsUrl($this->tenant, $this->survey, '?item=open.improve'))->assertInertia(function (Assert $page): void {
        $props = $page->toArray()['props'];
        expect($props['commentsState'])->toBe('shown')
            ->and($props['comments']['total'])->toBe(10)
            ->and(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('De un jefe');
    });
});

it('orders the originals by their random id, not by arrival', function (): void {
    companyComments($this->survey, 'open.best', 12);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $this->survey))->assertInertia(function (Assert $page): void {
        $bodies = collect($page->toArray()['props']['comments']['data'])->pluck('body')->all();
        expect($bodies)->toBe(Comment::query()->where('item_key', 'open.best')->orderBy('id')->limit(10)->pluck('body')->all());
    });
});

it('accepts one of the two open questions and nothing else', function (string $query): void {
    $this->actingAs($this->hr)->getJson(commentsUrl($this->tenant, $this->survey, $query))->assertUnprocessable();
})->with(['a manager question' => '?item=manager.keep', 'unknown' => '?item=salary', 'two' => '?item[]=open.best&item[]=open.improve']);

it('warns when there were mentions of harassment or violence, with no detail', function (): void {
    $this->survey->update(['sensitive_mentions' => true]);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $this->survey))
        ->assertInertia(fn (Assert $page) => $page->where('sensitiveMentions', true));
    $this->survey->update(['sensitive_mentions' => false]);
    $this->get(commentsUrl($this->tenant, $this->survey))->assertInertia(fn (Assert $page) => $page->where('sensitiveMentions', false));
});

it('offers the retry only for a failed summary and only to who manages climate', function (): void {
    $failed = CommentSummary::factory()->status(SummaryStatus::Failed)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $this->survey))
        ->assertInertia(fn (Assert $page) => $page->where('summary.status', 'failed')->where('retryId', $failed->id));
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(commentsUrl($this->tenant, $this->survey))
        ->assertInertia(fn (Assert $page) => $page->where('summary.status', 'failed')->where('retryId', null));
});

it('retries a failed summary and queues one job', function (): void {
    Queue::fake();
    $failed = CommentSummary::factory()->status(SummaryStatus::Failed)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs($this->hr)->post(route('tenant.climate.summaries.retry', [$this->tenant, $failed->id]))
        ->assertRedirect(commentsUrl($this->tenant, $this->survey));

    expect($failed->refresh()->status)->toBe(SummaryStatus::Pending);
    Queue::assertPushed(SummarizeComments::class, 1);
});

it('answers 409 when the summary is not failed, and does not let a viewer or a stranger retry', function (): void {
    Queue::fake();
    $ready = CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);
    $other = Tenant::factory()->create();
    $foreignSurvey = Survey::factory()->closed()->create(['tenant_id' => $other->id]);
    $foreign = CommentSummary::factory()->status(SummaryStatus::Failed)->create(['tenant_id' => $other->id, 'survey_id' => $foreignSurvey->id]);
    $url = route('tenant.climate.summaries.retry', [$this->tenant, $ready->id]);

    $this->actingAs($this->hr)->post($url)->assertStatus(409);
    $this->post(route('tenant.climate.summaries.retry', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
    Queue::assertNothingPushed();
});

it('says the comments come when the survey closes', function (): void {
    $open = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id, 'kind' => 'climate', 'name' => 'Otra']);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $open))
        ->assertInertia(fn (Assert $page) => $page->where('available', false)->where('commentsState', 'hidden')->where('summary', null));
    expect(AuditEntry::query()->where('event', 'engagement.comments_viewed')->count())->toBe(0);
});

it('writes a log and an audit entry that say which survey and question and carry no text', function (): void {
    Log::spy();
    companyComments($this->survey, 'open.best', 12);

    $this->actingAs($this->hr)->get(commentsUrl($this->tenant, $this->survey, '?item=open.best'))->assertOk();

    $entry = AuditEntry::query()->where('event', 'engagement.comments_viewed')->sole();
    expect($entry->subject_id)->toBe($this->survey->id)->and($entry->metadata)->toBe(['item' => 'open.best']);
    Log::shouldHaveReceived('info')->with('engagement.comments_viewed', ['survey_id' => $this->survey->id, 'item' => 'open.best'])->once();
});

it('does not open for another company, a pulse survey, a role that does not view climate or a stranger', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Survey::factory()->closed()->create(['tenant_id' => $other->id]);
    $pulse = Survey::factory()->closed()->pulse()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr);
    $this->get(commentsUrl($this->tenant, $foreign))->assertNotFound();
    $this->get(commentsUrl($this->tenant, $pulse))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(commentsUrl($this->tenant, $this->survey))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(commentsUrl($this->tenant, $this->survey))->assertNotFound();
});
