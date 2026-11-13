<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\PortalController;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Pérez']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

it('lives at /portal/{token}/seating, outside the panel, throttled like the rest of the portal', function (): void {
    expect(route('portal.seating.show', 'abc', absolute: false))->toBe('/portal/abc/seating');

    $show = Route::getRoutes()->getByName('portal.seating.show');
    $store = Route::getRoutes()->getByName('portal.seating.inputs.store');

    expect($show?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and($store?->gatherMiddleware())->toContain('throttle:portal-actions');
});

it('shows the person their own page: the company, their name, the notice and the DOF options of footwear and discomfort', function (): void {
    $this->get(route('portal.seating.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/portal')
            ->where('token', $this->token)
            ->where('company', 'Acme')
            ->where('name', 'Ana Ruiz')
            ->where('notice', fn (string $notice) => str_contains($notice, 'voluntari'))
            ->where('footwear.options.1', 'Ergonómico, con buen soporte y amortiguación')
            ->where('footwear.options.3', 'Inadecuado (duro, sin soporte, inseguro)')
            ->where('discomfort.options.1', 'Ninguno')
            ->where('discomfort.options.3', 'Dolor lumbar frecuente, hinchazón y/o calambres')
            ->where('sentOn', null));
});

it('shows only the person own last answer date, never anything of other people', function (): void {
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id, 'work_center_id' => $this->beto->work_center_id, 'note' => 'Nota de Beto', 'submitted_at' => '2026-02-01 12:00:00']);
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->ana->work_center_id, 'note' => 'Nota de Ana', 'submitted_at' => '2026-03-01 18:00:00']);

    $response = $this->get(route('portal.seating.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('sentOn', '2026-03-01'));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('Beto')->not->toContain('Nota de');
});

it("answers 'not valid' the same for a made-up link, another company's link, a replaced one and a person who left", function (): void {
    $other = Tenant::factory()->create();
    $stranger = EmployeeFactory::new()->create(['tenant_id' => $other->id]);
    workIn($other);
    $foreign = resolve(IssuePortalLink::class)->handle($stranger->id);
    workIn($this->tenant);
    $old = $this->token;
    resolve(IssuePortalLink::class)->handle($this->ana->id, revoke: true);
    $this->beto->forceFill(['deactivated_at' => now()])->save();
    $gone = resolve(IssuePortalLink::class)->handle($this->beto->id);

    $this->get(route('portal.seating.show', 'x'.str_repeat('0', 63)))->assertNotFound();
    $this->get(route('portal.seating.show', $old))->assertNotFound();
    $this->get(route('portal.seating.show', $gone))->assertNotFound();
    // A real link of another company opens THAT company's page, never ours.
    $this->get(route('portal.seating.show', $foreign))->assertOk()->assertInertia(fn (Assert $page) => $page->where('name', $stranger->name)->where('company', $other->trade_name));
});

// R2-employee-06: what waits for the person shows on the home of the portal, as the other tools do.
it('counts on the home of the portal the signed assessment the person has not confirmed', function (): void {
    $position = App\Modules\Seating\Models\StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->ana->work_center_id]);
    App\Modules\Seating\Models\PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $position->id]);
    $identity = new App\Modules\Telework\Data\PortalIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    $section = fn (): ?App\Modules\Telework\Data\PortalSectionLink => resolve(App\Modules\Seating\Support\SeatingPortalSection::class)->for($identity, $this->token);

    expect($section()?->pending)->toBeNull();

    $assessment = App\Modules\Seating\Models\RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $position->id, 'work_center_id' => $this->ana->work_center_id]);

    expect($section()?->pending)->toBe(1);

    App\Modules\Seating\Models\RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'assessment_id' => $assessment->id]);

    expect($section()?->pending)->toBeNull();
});
