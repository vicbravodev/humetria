<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\PunchWorkerSection;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PunchWorkerSection::class);

/*
 * «Marcar» in the person's start (`/me/{slug}`): it goes to the screen of Marcar of the company (`/a/{slug}`), which carries no
 * credential (each mark asks for its own signature), and only for a company that uses the registro de jornada (CUJ 9).
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
});

/** @return array<string, array<string, mixed>> The sections of the person's start, keyed by their key. */
function startSections(): array
{
    $sections = [];
    test()->get(route('worker.home', 'acme'))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $sections = collect($page->toArray()['props']['sections'])->keyBy('key')->all();
    });

    return $sections;
}

it('offers «Marcar» to a person of a company with the registro de jornada, at the screen of Marcar, with no credential', function (): void {
    enteredAs($this->ana->id);

    expect(startSections()['attendance'])->toBe([
        'key' => 'attendance',
        'label' => 'Marcar',
        'description' => 'Marque su entrada y su salida, y vea su semana.',
        'href' => route('attendance.device.punch.home', 'acme'),
        'pending' => null,
    ]);
});

it('offers nothing of marking to a company without the registro de jornada', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
    enteredAs($this->ana->id);

    expect(startSections())->not->toHaveKey('attendance');
});
