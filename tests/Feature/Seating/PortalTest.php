<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\PortalController;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Support\PortalAssessment;
use App\Modules\Seating\Support\SeatingWorkerSection;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalController::class, SeatingWorkerSection::class, PortalAssessment::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Pérez']);
    workIn($this->tenant);
    $this->slug = asPerson($this->ana->id);
});

it('lives at /me/{slug}/seating, one task per page, in the person\'s pages, throttled like the rest of them', function (): void {
    expect(route('portal.seating.show', 'acme', absolute: false))->toBe('/me/acme/seating')
        ->and(route('portal.seating.answers', 'acme', absolute: false))->toBe('/me/acme/seating/answers')
        ->and(route('portal.seating.report', 'acme', absolute: false))->toBe('/me/acme/seating/report');

    foreach (['portal.seating.show', 'portal.seating.answers', 'portal.seating.report'] as $name) {
        expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())->toContain('throttle:portal-views');
    }
    expect(Route::getRoutes()->getByName('portal.seating.inputs.store')?->gatherMiddleware())->toContain('throttle:portal-actions');
});

// Finding 13: the page is a list of tasks; each one opens its own screen. Nothing of health nor the person's name is on it.
it('shows the person the list of their Ley Silla tasks, with no answers, notice nor name', function (): void {
    $this->get(route('portal.seating.show', $this->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/portal')
            ->where('slug', $this->tenant->slug)
            ->where('company', 'Acme')
            ->where('homeUrl', route('worker.home', $this->tenant->slug))
            ->where('pending', false)
            ->where('acknowledgedOn', null)
            ->where('sentOn', null)
            ->missing('notice')
            ->missing('footwear')
            ->missing('acknowledgment'));
});

it('names the person on none of their Ley Silla pages', function (string $name): void {
    $response = $this->get(route($name, $this->slug))->assertOk();

    expect(json_encode($response->viewData('page')['props']))->not->toContain('Ana Ruiz');
})->with(['portal.seating.show', 'portal.seating.answers', 'portal.seating.report']);

it('opens the two questions on their own page: the company, the notice and the DOF options of footwear and discomfort', function (): void {
    $this->get(route('portal.seating.answers', $this->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/portal-answers')
            ->where('slug', $this->tenant->slug)
            ->where('company', 'Acme')
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

    foreach (['portal.seating.show', 'portal.seating.answers'] as $name) {
        $response = $this->get(route($name, $this->slug))
            ->assertInertia(fn (Assert $page) => $page->where('sentOn', '2026-03-01'));

        expect(json_encode($response->viewData('page')['props']))->not->toContain('Beto')->not->toContain('Nota de');
    }
});

it('sends a visitor to enter, ends the page for a person who left, and opens nothing of another company with this session', function (): void {
    $other = Tenant::factory()->create();
    $stranger = EmployeeFactory::new()->create(['tenant_id' => $other->id]);

    $this->get(route('portal.seating.show', $other->slug))->assertRedirect(route('worker.home', $other->slug));

    $this->beto->forceFill(['deactivated_at' => now()])->save();
    $slug = asPerson($this->beto->id);
    $this->get(route('portal.seating.show', $slug))->assertRedirect(route('worker.home', $slug));

    session()->flush();
    $this->get(route('portal.seating.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));
    // The person of another company opens THAT company's page, never ours.
    $this->get(route('portal.seating.show', asPerson($stranger->id)))->assertOk()->assertInertia(fn (Assert $page) => $page->where('company', $other->trade_name));
});

it('sends a visitor of the questions or of the seat report to enter, and opens only their own company', function (string $name): void {
    $other = Tenant::factory()->create();

    $this->get(route($name, $other->slug))->assertRedirect(route('worker.home', $other->slug));
    session()->flush();
    $this->get(route($name, $this->slug))->assertRedirect(route('worker.home', $this->slug));
})->with(['portal.seating.answers', 'portal.seating.report']);

// R2-employee-06: what waits for the person shows on their start, as the other tools do.
it('counts on the person\'s start the signed assessment the person has not confirmed, at their page with no credential', function (): void {
    $position = App\Modules\Seating\Models\StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->ana->work_center_id]);
    App\Modules\Seating\Models\PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $position->id]);
    $identity = new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    $section = fn (): ?App\Modules\WorkerAccess\Data\WorkerSectionLink => resolve(SeatingWorkerSection::class)->for($identity);

    expect($section()?->pending)->toBeNull()
        ->and($section()?->key)->toBe('seating')
        ->and($section()?->href)->toBe(route('portal.seating.show', $this->tenant->slug))
        ->and(resolve(SeatingWorkerSection::class)->for(new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->beto->id, 'Acme', 'Beto Pérez')))->toBeNull();

    $assessment = App\Modules\Seating\Models\RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $position->id, 'work_center_id' => $this->ana->work_center_id]);

    expect($section()?->pending)->toBe(1);

    App\Modules\Seating\Models\RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'assessment_id' => $assessment->id]);

    expect($section()?->pending)->toBeNull();
});
