<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('fails when a prop carries the id of a stored response', function (): void {
    $survey = climateResultsSurvey($this->tenant, 1);
    $id = Response::query()->where('survey_id', $survey->id)->sole()->id;

    expect(fn () => expectNoEngagementResponses(['rows' => [['ref' => $id]]]))->toThrow(ExpectationFailedException::class);
});

it('fails when a prop carries the id or the text of a stored comment', function (): void {
    $survey = climateResultsSurvey($this->tenant, 1);
    $comment = Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'body' => 'Falta personal en la bodega']);

    expect(fn () => expectNoEngagementResponses(['c' => $comment->id]))->toThrow(ExpectationFailedException::class)
        ->and(fn () => expectNoEngagementResponses(['quote' => 'Falta personal en la bodega']))->toThrow(ExpectationFailedException::class);
});

it('fails when a prop is a map from a question key to a number, or a map named answers', function (): void {
    expect(fn () => expectNoEngagementResponses(['person' => ['clarity.goals' => 4]]))->toThrow(ExpectationFailedException::class)
        ->and(fn () => expectNoEngagementResponses(['answers' => ['x' => 'y']]))->toThrow(ExpectationFailedException::class);
});

it('lets aggregates through, a question key as a value included', function (): void {
    climateResultsSurvey($this->tenant, 1);

    expectNoEngagementResponses(['items' => [['key' => 'clarity.goals', 'favorable' => 70, 'answers_count' => 12]], 'index' => 4]);

    expect(true)->toBeTrue();
});

/**
 * A closed and published climate survey with twelve responses, twelve comments of the company and twelve of a manager's
 * group, and the manager's summary.
 *
 * @return array{Survey, Employee}
 */
function privacySweepSurvey(Tenant $tenant): array
{
    $ana = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $survey = climateManagerSurvey($tenant, [$ana->id => [12, 4]]);
    $survey->update(['results_published_at' => now()]);

    foreach (range(1, 12) as $i) {
        Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'item_key' => 'open.best', 'body' => "Comentario secreto de empresa {$i}"]);
        Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'item_key' => 'manager.keep', 'comment_group_id' => $ana->id, 'body' => "Comentario secreto de equipo {$i}"]);
    }
    CommentSummary::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $ana->id]);
    Commitment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id]);

    return [$survey, $ana];
}

it('keeps every individual response and comment out of the props of every page of climate, for RH and the portal', function (): void {
    [$survey, $ana] = privacySweepSurvey($this->tenant);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $visited = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();
        if ($name === null || ! str_starts_with($name, 'tenant.climate.') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $response = $this->get(route($name, ['tenant' => $this->tenant, 'survey' => $survey, 'manager' => $ana->id]))->assertOk();
        $visited[] = $name;

        if (in_array($name, ['tenant.climate.comments', 'tenant.climate.managers.show'], true)) {
            // The two pages where RH reads originals (from ten) by design, of the company or of one group, never the
            // other's: and no id and no person's answers.
            $other = $name === 'tenant.climate.comments' ? 'Comentario secreto de equipo' : 'Comentario secreto de empresa';
            $response->assertInertia(function (Assert $page) use ($other): void {
                $props = $page->toArray()['props'];
                expect(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain($other)
                    ->and(engagementAnswersIn($props))->toBe([]);
                foreach (Response::query()->withoutGlobalScopes()->pluck('id') as $id) {
                    expect(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain($id);
                }
            });
        } elseif ($name !== 'tenant.climate.export') {
            $response->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
        }
    }

    // The two pages of the portal, for the manager of the group.
    $link = (string) resolve(IssuePortalLink::class)->handle($ana->id);
    foreach (['portal.climate.show', 'portal.feedback.show'] as $name) {
        $this->get(route($name, $link))->assertOk()->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
        $visited[] = $name;
    }

    expect($visited)->toContain('tenant.climate.index', 'tenant.climate.show', 'tenant.climate.results', 'tenant.climate.results.items', 'tenant.climate.comments', 'tenant.climate.commitments.index', 'tenant.climate.managers', 'tenant.climate.managers.show', 'portal.climate.show', 'portal.feedback.show');
});

it('names every route of climate and puts it behind auth, unless it is a link with its own token', function (): void {
    $tokenLinks = [
        'engagement.survey.show', // the person opens the survey from their email; the token is the credential
        'engagement.survey.submit', // sends the survey once; the token is the credential
        'portal.climate.show', // what the company will do; the portal token is the credential
        'portal.feedback.show', // the manager's feedback; the portal token is the credential
    ];
    $seen = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        $isClimate = str_contains($uri, '/climate') || str_starts_with($uri, 'engagement/') || (str_starts_with($uri, 'portal/') && str_ends_with($uri, '/feedback'));
        if (! $isClimate) {
            continue;
        }

        $seen++;
        $name = (string) $route->getName();
        expect($name)->not->toBe('');
        expect(in_array('auth', $route->gatherMiddleware(), true) || in_array($name, $tokenLinks, true))->toBeTrue("{$name} has no auth and is not a link with its own token");
    }

    expect($seen)->toBeGreaterThan(20);
});

it('keeps every individual response out of the props of every page of the pulse, the trend included', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    $pulse = pulseResultsSurvey($this->tenant, App\Modules\Engagement\Enums\PulseMode::Manager, 12, manager: $ana->id);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $pulse->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $visited = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();
        if ($name === null || ! str_starts_with($name, 'tenant.pulse.') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $response = $this->get(route($name, ['tenant' => $this->tenant, 'survey' => $pulse, 'manager' => $ana->id]))->assertOk();
        $visited[] = $name;

        if ($name !== 'tenant.pulse.export') {
            $response->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
        }
    }

    expect($visited)->toContain('tenant.pulse.index', 'tenant.pulse.show', 'tenant.pulse.results', 'tenant.pulse.results.items', 'tenant.pulse.commitments.index', 'tenant.pulse.managers', 'tenant.pulse.managers.show', 'tenant.pulse.trend');
});

it('names every route of the pulse and puts it behind auth: its link is the one of the survey, with its own token', function (): void {
    $seen = 0;

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_contains($route->uri(), '/pulse')) {
            continue;
        }

        $seen++;
        $name = (string) $route->getName();
        expect($name)->toStartWith('tenant.pulse.')->and(in_array('auth', $route->gatherMiddleware(), true))->toBeTrue("{$name} has no auth");
    }

    expect($seen)->toBeGreaterThan(15);
});

it('has the keys of its knobs in .env.example, and no file names the metric it must not use', function (): void {
    $env = File::get(base_path('.env.example'));
    expect($env)->toContain('ENGAGEMENT_MIN_COMMENTS=')->toContain('ENGAGEMENT_MIN_TREND=');

    foreach (['resources/js', 'lang', 'app'] as $dir) {
        foreach (File::allFiles(base_path($dir)) as $file) {
            $text = File::get($file->getPathname());
            expect(stripos($text, 'eNPS'))->toBeFalse("{$file->getPathname()} names eNPS")
                ->and(stripos($text, 'Net Promoter'))->toBeFalse("{$file->getPathname()} names Net Promoter");
        }
    }
});
