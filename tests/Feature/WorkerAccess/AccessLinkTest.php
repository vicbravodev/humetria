<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\OpenAccessLink;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenAccessLink::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
});

function linkOf(Employee $person, string $token, ?Closure $state = null): WorkerAccessLink
{
    $factory = WorkerAccessLink::factory()->withToken($token);

    return ($state === null ? $factory : $state($factory))->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id]);
}

/** The one screen of a link that does not work, whatever the cause. */
function expectInvalidLink(string $slug, string $token): void
{
    test()->get(route('worker.link.show', [$slug, $token]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('worker-access/link')->where('openUrl', null)->where('homeUrl', route('worker.home', $slug))->etc());
}

it('shows «Continuar» for a link that works, and opening the page spends nothing', function (): void {
    $link = linkOf($this->ana, 'good-token');

    foreach (range(1, 3) as $ignored) {
        $this->get(route('worker.link.show', ['acme', 'good-token']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('worker-access/link')
                ->where('company', 'Acme')
                ->where('openUrl', route('worker.link.open', ['acme', 'good-token']))
                ->etc())
            ->assertDontSee('Ana López');
    }

    expect($link->refresh()->used_at)->toBeNull();
});

it('spends the link and opens the session of its person, as the kind of link says', function (string $kind, string $via): void {
    linkOf($this->ana, 'good-token', fn ($factory) => $kind === 'requested' ? $factory->requested() : $factory);
    Log::spy();

    $this->post(route('worker.link.open', ['acme', 'good-token']))->assertRedirect(route('worker.home', 'acme'));

    $session = resolve(WorkerSessionStore::class)->open();
    expect($session?->identity->employeeId)->toBe($this->ana->id)->and($session?->via->value)->toBe($via)
        ->and(WorkerAccessLink::query()->sole()->used_at?->toIso8601String())->toBe('2026-11-16T15:00:00+00:00');
    Log::shouldHaveReceived('info')->with('worker_access.entered', ['employee_id' => $this->ana->id, 'via' => $via]);
})->with([['issued', 'issued_link'], ['requested', 'requested_link']]);

it('opens a link once: the second time it shows the screen of a link that does not work', function (): void {
    linkOf($this->ana, 'good-token');

    $this->post(route('worker.link.open', ['acme', 'good-token']))->assertRedirect(route('worker.home', 'acme'));
    resolve(WorkerSessionStore::class)->end();

    $this->post(route('worker.link.open', ['acme', 'good-token']))->assertRedirect(route('worker.link.show', ['acme', 'good-token']));
    expect(session()->has('worker'))->toBeFalse();
    expectInvalidLink('acme', 'good-token');
});

it('lets only one of two requests that spend the same link at once win', function (): void {
    $link = linkOf($this->ana, 'good-token');
    $first = WorkerAccessLink::findPending('good-token');
    $second = WorkerAccessLink::findPending('good-token');

    expect([$first?->spend(), $second?->spend()])->toBe([true, false])
        ->and($link->refresh()->used_at)->not->toBeNull();
});

it('keeps an issued link 48 hours and a requested one 30 minutes, to the second', function (string $kind, int $seconds): void {
    linkOf($this->ana, 'good-token', fn ($factory) => ($kind === 'requested' ? $factory->requested() : $factory)->state(['expires_at' => now()->addSeconds($seconds)]));

    $this->travel($seconds - 1)->seconds();
    $this->get(route('worker.link.show', ['acme', 'good-token']))->assertInertia(fn (Assert $page) => $page->where('openUrl', route('worker.link.open', ['acme', 'good-token']))->etc());

    $this->travel(1)->seconds();
    expectInvalidLink('acme', 'good-token');
    $this->post(route('worker.link.open', ['acme', 'good-token']))->assertRedirect(route('worker.link.show', ['acme', 'good-token']));
})->with([['issued', 48 * 3600], ['requested', 30 * 60]]);

it('shows the same screen for a link revoked, expired, unknown, of another company, or of a person who left', function (string $case): void {
    $token = 'the-token';
    $slug = 'acme';

    match ($case) {
        'revoked' => linkOf($this->ana, $token, fn ($factory) => $factory->revoked()),
        'expired' => linkOf($this->ana, $token, fn ($factory) => $factory->expired()),
        'unknown' => null,
        'of another company' => linkOf(EmployeeFactory::new()->create(['tenant_id' => Tenant::factory()->create(['slug' => 'other'])->id]), $token),
        'of a person who left' => linkOf(EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]), $token),
    };

    expectInvalidLink($slug, $token);
    $this->post(route('worker.link.open', [$slug, $token]))->assertRedirect(route('worker.link.show', [$slug, $token]));
    expect(session()->has('worker'))->toBeFalse();
})->with(['revoked', 'expired', 'unknown', 'of another company', 'of a person who left']);

it('does not open in company A a link of company B, even with its own slug in the address', function (): void {
    $other = Tenant::factory()->create(['slug' => 'other']);
    $beto = EmployeeFactory::new()->create(['tenant_id' => $other->id]);
    linkOf($beto, 'beto-token');
    workIn($this->tenant);

    $this->post(route('worker.link.open', ['acme', 'beto-token']))->assertRedirect(route('worker.link.show', ['acme', 'beto-token']));
    $this->post(route('worker.link.open', ['other', 'beto-token']))->assertRedirect(route('worker.home', 'other'));
});

it('slows down a link opened too often, whatever the address', function (): void {
    foreach (range(1, 10) as $ignored) {
        $this->get(route('worker.link.show', ['acme', 'guess']))->assertOk();
    }

    $this->get(route('worker.link.show', ['acme', 'guess']))->assertTooManyRequests();
});
