<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Http\Controllers\PortalController;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalPending::class, PortalController::class, App\Modules\Telework\Support\SessionPortal::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
});

/** A workplace of the employee with its application, as registering an aspirant leaves them. */
function portalApplication(Employee $employee, array $application = []): void
{
    $teleworker = Teleworker::query()->where('employee_id', $employee->id)->first()
        ?? TeleworkerFactory::new()->create(['tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id, 'work_center_id' => $employee->work_center_id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $teleworker->tenant_id, 'teleworker_id' => $teleworker->id]);
    ChecklistApplicationFactory::new()->create(['tenant_id' => $teleworker->tenant_id, 'workplace_id' => $workplace->id, ...$application]);
}

/** @return array<string, mixed> The props of the telework section of the employee, entering as them. */
function sectionProps(Employee $employee): array
{
    $response = test()->get(route('portal.telework.show', asPerson($employee->id)));
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    unset($props['errors']);

    return $props;
}

it('lives at /me/{slug}/telework, in the person\'s pages', function (): void {
    expect(route('portal.telework.show', 'acme', absolute: false))->toBe('/me/acme/telework');
});

it('shows the person their pending checklists, one per workplace', function (): void {
    portalApplication($this->employee);
    portalApplication($this->employee);

    $this->get(route('portal.telework.show', asPerson($this->employee->id)))
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/portal-section')
            ->where('slug', $this->tenant->slug)
            ->where('company', 'Acme')
            ->where('canNotify', true)
            ->has('pending.checklists', 2, fn (Assert $item) => $item
                ->has('id')
                ->where('workplace', 'Calle Hidalgo 45, Centro, Querétaro, Querétaro')
                ->where('section', 1)
                ->where('label', 'Pendiente de contestar'))
            ->has('pending.sent', 0));
});

it('labels a sent checklist as under review, never with an outcome', function (): void {
    portalApplication($this->employee, ['submitted_at' => now()]);

    $props = sectionProps($this->employee);

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

    expect(sectionProps($this->employee)['pending']['checklists'])->toBe([]);
});

it('shows nothing of other people', function (): void {
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Otro']);
    portalApplication($other);
    portalApplication($this->employee);

    $json = json_encode(sectionProps($this->employee), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Beto')->not->toContain($other->id)
        ->and(sectionProps($this->employee)['pending']['checklists'])->toHaveCount(1);
});

it('opens for a person who does not telework, with nothing pending yet', function (): void {
    $props = sectionProps($this->employee);

    expect($props['canNotify'])->toBeFalse()
        ->and($props['pending'])->toBe(['checklists' => [], 'sent' => [], 'photos' => [], 'visits' => [], 'documents' => [], 'equipment' => []]);
});

it('follows the open period of the person', function (): void {
    portalApplication($this->employee);

    expect(teleworkPortalOf($this->employee->id)->teleworkerId)->not->toBeNull();
});

it('lists the checklists of a workplace in the order they were asked, whatever the order they were stored', function (): void {
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $later = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $workplace->id, 'section' => 2, 'requested_at' => '2026-02-20 12:00:00']);
    $earlier = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $workplace->id, 'section' => 1, 'requested_at' => '2026-02-01 12:00:00']);

    $sent = resolve(PortalPending::class)->for(teleworkPortalOf($this->employee->id))->sent;

    expect(array_map(fn ($item): string => $item->id, $sent))->toBe([$earlier->id, $later->id]);
});

it('lists two checklists asked at the same moment by their id', function (): void {
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    foreach (['ffffffff-ffff-4fff-bfff-ffffffffffff' => 2, '00000000-0000-4000-8000-000000000000' => 1] as $id => $section) {
        ChecklistApplicationFactory::new()->submitted()->create(['id' => $id, 'tenant_id' => $this->tenant->id, 'workplace_id' => $workplace->id, 'section' => $section, 'requested_at' => '2026-02-01 12:00:00']);
    }

    $sent = resolve(PortalPending::class)->for(teleworkPortalOf($this->employee->id))->sent;

    expect(array_map(fn ($item): string => $item->id, $sent))->toBe(['00000000-0000-4000-8000-000000000000', 'ffffffff-ffff-4fff-bfff-ffffffffffff']);
});

it('answers 404 on every telework page of the portal when the company has no NOM-037', function (string $method, string $route): void {
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
    portalApplication($this->employee);
    $id = (string) Str::uuid();

    // Parameters a route does not name become its query string, which the 404 does not read. Not even a visitor is asked to enter.
    $this->{$method}(route($route, ['tenant' => $this->tenant->slug, 'application' => $id, 'evidenceRequest' => $id, 'authorization' => $id, 'document' => $id, 'item' => $id]))->assertNotFound();
    asPerson($this->employee->id);
    $this->{$method}(route($route, ['tenant' => $this->tenant->slug, 'application' => $id, 'evidenceRequest' => $id, 'authorization' => $id, 'document' => $id, 'item' => $id]))->assertNotFound();
})->with([
    ['get', 'portal.telework.show'],
    ['get', 'portal.checklist.show'],
    ['post', 'portal.checklist.store'],
    ['post', 'portal.evidence.store'],
    ['post', 'portal.visits.confirm'],
    ['get', 'portal.documents.file'],
    ['post', 'portal.documents.acknowledge'],
    ['post', 'portal.equipment.respond'],
    ['get', 'portal.notices.show'],
    ['post', 'portal.notices.store'],
]);

it('takes a document, the equipment or a visit confirmed only after a recent proof that it is the person', function (string $route, string $parameter, EntryVia $via, int $minutes): void {
    portalApplication($this->employee);
    enteredAs($this->employee->id, $via);
    $this->travel($minutes)->minutes();
    $page = route('portal.telework.show', $this->tenant->slug);
    $address = route($route, ['tenant' => $this->tenant->slug, $parameter => (string) Str::uuid()]);

    // Without it the tool is never reached: back to the page with the step to prove.
    $this->from($page)->post($address, ['received' => '1'])->assertRedirect($page)->assertInertiaFlash('proofRequired');

    // With it the tool answers as before (here, a 404 for a record that is not the person's).
    resolve(WorkerSessionStore::class)->prove();
    $this->from($page)->post($address, ['received' => '1'])->assertNotFound();
})->with([
    'a document' => ['portal.documents.acknowledge', 'document'],
    'the equipment' => ['portal.equipment.respond', 'item'],
    'a visit' => ['portal.visits.confirm', 'authorization'],
])->with([
    'entered with a link' => [EntryVia::RequestedLink, 0],
    'proved 5 minutes ago' => [EntryVia::Key, 5],
]);

it('throttles the person\'s telework pages per browser session, never one bucket for everyone', function (): void {
    $slug = asPerson($this->employee->id);
    $cookie = config()->string('session.cookie');
    $session = $this->get(route('portal.telework.show', $slug))->assertOk()->getCookie($cookie)?->getValue() ?? throw new LogicException;

    foreach (range(2, 30) as $ignored) {
        $this->withCredentials()->withCookie($cookie, $session)->get(route('portal.telework.show', $slug))->assertOk();
    }

    $this->withCredentials()->withCookie($cookie, $session)->get(route('portal.telework.show', $slug))->assertTooManyRequests();

    $key = function (string $id) use ($slug): string {
        $request = Request::create("/me/{$slug}/telework");
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession(tap(session()->driver())->setId($id));

        return RateLimiter::limiter('portal-views')($request)[0]->key;
    };
    $one = str_repeat('a', 40);

    expect($key($one))->toBe('portal-session:'.hash('sha256', $one))->not->toBe($key(str_repeat('b', 40)));

    // A generous cap per address, the same for every session behind it; sending is throttled harder.
    $limits = function (string $name) use ($slug): array {
        $request = Request::create("/me/{$slug}/telework", server: ['REMOTE_ADDR' => '203.0.113.9']);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession(tap(session()->driver())->setId(str_repeat('c', 40)));

        return RateLimiter::limiter($name)($request);
    };
    expect(array_map(fn ($limit): int => $limit->maxAttempts, $limits('portal-views')))->toBe([30, 600])
        ->and(array_map(fn ($limit): int => $limit->maxAttempts, $limits('portal-actions')))->toBe([10, 300])
        ->and($limits('portal-views')[1]->key)->toBe('portal-address:203.0.113.9')
        ->and($limits('portal-actions')[1]->key)->toBe('portal-actions-address:203.0.113.9');
});

it('sends a visitor of the telework section to enter, and no page of another company opens with this session', function (): void {
    $other = Tenant::factory()->create();

    $this->get(route('portal.telework.show', $this->tenant->slug))->assertRedirect(route('worker.home', $this->tenant->slug));

    asPerson($this->employee->id);
    $this->get(route('portal.telework.show', $other->slug))->assertRedirect(route('worker.home', $other->slug));
});

it('ends the section for a person who left, and answers 404 for a blocked company', function (): void {
    $slug = asPerson($this->employee->id);
    Employee::query()->whereKey($this->employee->id)->update(['deactivated_at' => now()]);

    $this->get(route('portal.telework.show', $slug))->assertRedirect(route('worker.home', $slug));

    $slug = asPerson(EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id);
    Tenant::query()->whereKey($this->tenant->id)->update(suspension());
    $this->get(route('portal.telework.show', $slug))->assertNotFound();
});
