<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Http\Controllers\PortalController;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(IssuePortalLink::class, OpenPortal::class, PortalPending::class, PortalController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
});

/** A workplace of the employee with its application, as registering an aspirant leaves them. */
function portalApplication(Employee $employee, array $application = []): void
{
    $teleworker = Teleworker::query()->where('employee_id', $employee->id)->first()
        ?? TeleworkerFactory::new()->create(['tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id, 'work_center_id' => $employee->work_center_id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $teleworker->tenant_id, 'teleworker_id' => $teleworker->id]);
    ChecklistApplicationFactory::new()->create(['tenant_id' => $teleworker->tenant_id, 'workplace_id' => $workplace->id, ...$application]);
}

/** @return array<string, mixed> The props of the portal page for a token. */
function portalProps(string $token): array
{
    $response = test()->get(route('portal.show', $token));
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    unset($props['errors']);

    return $props;
}

it('lives at /portal/{token}, outside the panel', function (): void {
    expect(route('portal.show', 'abc', absolute: false))->toBe('/portal/abc');
});

it('issues a link whose plain token is never stored and replaces the previous one', function (): void {
    $first = $this->token;
    $second = resolve(IssuePortalLink::class)->handle($this->employee->id);

    expect($second)->not->toBe($first)->and(strlen($second))->toBeGreaterThanOrEqual(40)
        ->and(PortalLink::query()->count())->toBe(1)
        ->and(PortalLink::query()->sole()->token_hash)->toBe(hash('sha256', $second))
        ->and(PortalLink::query()->where('token_hash', $second)->exists())->toBeFalse();

    $this->get(route('portal.show', $first))->assertInertia(fn (Assert $page) => $page->where('unavailable', true));
    $this->get(route('portal.show', $second))->assertInertia(fn (Assert $page) => $page->where('unavailable', false));
});

it('logs the issued link with ids only', function (): void {
    Log::spy();

    $token = resolve(IssuePortalLink::class)->handle($this->employee->id);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.portal_link_issued'
        && $context === ['employee_id' => $this->employee->id]
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $token))->once();
});

it('shows the person their pending checklists, one per workplace', function (): void {
    portalApplication($this->employee);
    portalApplication($this->employee);

    $this->get(route('portal.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/portal')
            ->where('unavailable', false)
            ->where('company', 'Acme')
            ->where('name', 'Ana Ruiz')
            ->has('pending.checklists', 2, fn (Assert $item) => $item
                ->has('id')
                ->where('workplace', 'Calle Hidalgo 45, Centro, Querétaro, Querétaro')
                ->where('section', 1)
                ->where('label', 'Pendiente de contestar'))
            ->has('pending.sent', 0));
});

it('labels a sent checklist as under review, never with an outcome', function (): void {
    portalApplication($this->employee, ['submitted_at' => now()]);

    $props = portalProps($this->token);

    expect($props['pending']['checklists'])->toBe([])
        ->and($props['pending']['sent'][0]['label'])->toBe('En revisión de la Comisión');
    // The Comisión's decision is not the person's to read here (criterio C-11): RH tells them.
    foreach (['decision', 'valid', 'not_valid', 'valid_with_plan', 'notes', 'action_plan'] as $word) {
        expect(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain("\"{$word}\"")->not->toContain($word.'"');
    }
});

it('does not show retired workplaces', function (): void {
    portalApplication($this->employee);
    Workplace::query()->update(['retired_at' => now()]);

    expect(portalProps($this->token)['pending']['checklists'])->toBe([]);
});

it('shows nothing of other people', function (): void {
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Otro']);
    portalApplication($other);
    portalApplication($this->employee);

    $json = json_encode(portalProps($this->token), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Beto')->not->toContain($other->id)
        ->and(portalProps($this->token)['pending']['checklists'])->toHaveCount(1);
});

it('opens for a person who does not telework, with nothing pending yet', function (): void {
    $props = portalProps($this->token);

    expect($props['unavailable'])->toBeFalse()
        ->and($props['pending'])->toBe(['checklists' => [], 'sent' => []]);
});

it('answers an unknown, replaced or deactivated-employee token with the same page and status', function (): void {
    $replaced = $this->token;
    resolve(IssuePortalLink::class)->handle($this->employee->id);
    $gone = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]);
    $deactivated = resolve(IssuePortalLink::class)->handle($gone->id);

    $unknown = portalProps('not-a-token');

    expect($unknown['unavailable'])->toBeTrue()
        ->and(portalProps($replaced))->toBe($unknown)
        ->and(portalProps($deactivated))->toBe($unknown);
});

it('answers the same page for a company that may not be used', function (): void {
    Tenant::query()->whereKey($this->tenant->id)->update(['deactivated_at' => now()]);

    expect(portalProps($this->token)['unavailable'])->toBeTrue()
        ->and(portalProps($this->token))->toBe(portalProps('not-a-token'));
});

it('opens the portal inside the company of the link', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);

    $opened = resolve(OpenPortal::class)->handle($this->token);

    expect($opened->tenantId)->toBe($this->tenant->id)
        ->and($opened->employeeId)->toBe($this->employee->id)
        ->and($opened->teleworkerId)->toBeNull()
        ->and($opened->company)->toBe('Acme')
        ->and(resolve(CurrentTenant::class)->id())->toBe($this->tenant->id);

    expect(fn () => resolve(OpenPortal::class)->handle('nope'))->toThrow(PortalUnavailable::class);
});

it('follows the open period of the person', function (): void {
    portalApplication($this->employee);

    expect(resolve(OpenPortal::class)->handle($this->token)->teleworkerId)->not->toBeNull();
});

it('throttles per link and keeps the address cap separate', function (): void {
    foreach (range(1, 30) as $ignored) {
        $this->get(route('portal.show', 'one-link'))->assertOk();
    }

    $this->get(route('portal.show', 'one-link'))->assertTooManyRequests();
    // Another link from the same address is still served: a room on one Wi-Fi can open theirs at once.
    $this->get(route('portal.show', 'another-link'))->assertOk();

    $limit = fn (string $name, string $token): array => RateLimiter::limiter($name)(portalRequest($token));
    $views = $limit('portal-views', 'one-link');
    $actions = $limit('portal-actions', 'one-link');

    expect(array_map(fn ($limit): int => $limit->maxAttempts, $views))->toBe([30, 600])
        ->and(array_map(fn ($limit): int => $limit->maxAttempts, $actions))->toBe([10, 300])
        // The key is the link's hash, never the link; another link has another key and the address one is shared.
        ->and($views[0]->key)->toBe('portal:'.hash('sha256', 'one-link'))
        ->and($limit('portal-views', 'another-link')[0]->key)->not->toBe($views[0]->key)
        ->and($limit('portal-views', 'another-link')[1]->key)->toBe($views[1]->key);
});

/** A request to the portal route with its token bound, as the limiter sees it. */
function portalRequest(string $token): Request
{
    $request = Request::create("/portal/{$token}");
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}
