<?php

declare(strict_types=1);

use App\Modules\Disclosure\Http\Controllers\DisseminationController;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\AckProgress;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DisseminationController::class, AckProgress::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id, 'published_at' => '2026-11-10 15:00:00']);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'work_center_id' => $this->plant->id]);
    $this->url = fn (string $name = 'dissemination', mixed ...$parameters): string => route("tenant.disclosure.{$name}", [$this->tenant, ...$parameters]);
});

/** A person asked for `$policy`; confirmed on `$channel` when given. */
function askedAndMaybeConfirmed(object $test, string $name, ?string $channel = null, ?Document $policy = null, bool $active = true): Employee
{
    $person = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->plant->id, 'name' => $name, 'email' => $name === 'Sin Correo' ? null : fake()->unique()->safeEmail()]);
    Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => ($policy ?? $test->policy)->id, 'employee_id' => $person->id, 'channel' => $channel, 'acknowledged_at' => $channel === null ? null : '2026-11-12 10:00:00', ...($channel === 'manual' ? ['note' => 'Hoja 1', 'recorded_by' => $test->hr->id] : [])]);

    if (! $active) {
        $person->update(['deactivated_at' => now()]);
    }

    return $person;
}

it('is for whoever manages the disclosure: the viewer, the Comisión and the handlers have no part in it', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(($this->url)())->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
    'complaints' => [TenantRole::Complaints, 403],
]);

it('has nothing to follow in a company without a published policy', function (): void {
    $other = Tenant::factory()->create(['slug' => 'beta']);
    $owner = memberOf($other, TenantRole::Owner);

    $this->actingAs($owner)->get(route('tenant.disclosure.dissemination', $other))
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/dissemination')->where('version', null)->where('progress', null)->where('people.total', 0));
});

it('shows how many of the active people confirmed the latest version, as a share of them', function (): void {
    askedAndMaybeConfirmed($this, 'Ana Ruiz', 'portal');
    askedAndMaybeConfirmed($this, 'Beto Peña', 'manual');
    askedAndMaybeConfirmed($this, 'Carla Soto');
    askedAndMaybeConfirmed($this, 'Diego Paz');
    askedAndMaybeConfirmed($this, 'Elena Gil');
    askedAndMaybeConfirmed($this, 'Ya Se Fue', 'portal', active: false);

    $this->actingAs($this->hr)->get(($this->url)())
        ->assertInertia(fn (Assert $page) => $page
            ->where('version', fn ($version) => $version['id'] === $this->policy->id && $version['version'] === 1 && $version['centers'] === 1 && $version['publishedOn'] === '10 nov 2026')
            ->where('progress', ['active' => 5, 'confirmed' => 2, 'pending' => 3, 'percent' => 40])
            ->where('counts', ['all' => 5, 'pending' => 3, 'confirmed' => 2])
            ->where('people.total', 5));
});

it('lists the people by name, a page of 15 at a time, with what is recorded of each and no email', function (): void {
    askedAndMaybeConfirmed($this, 'Ana Ruiz', 'portal');
    askedAndMaybeConfirmed($this, 'Beto Peña', 'manual');
    askedAndMaybeConfirmed($this, 'Carla Soto');
    askedAndMaybeConfirmed($this, 'Sin Correo');

    $this->actingAs($this->hr)->get(($this->url)())
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.per_page', 15)
            ->where('people.data.0', fn ($row) => $row['name'] === 'Ana Ruiz' && $row['state'] === 'confirmed' && $row['channelLabel'] === 'Desde su enlace' && $row['on'] === '12 nov 2026' && $row['hasEmail'] === true && isset($row['id']) && ! isset($row['email']))
            ->where('people.data.1', fn ($row) => $row['name'] === 'Beto Peña' && $row['channelLabel'] === 'En papel')
            ->where('people.data.2', fn ($row) => $row['name'] === 'Carla Soto' && $row['state'] === 'pending' && $row['on'] === null && $row['channelLabel'] === null)
            ->where('people.data.3.hasEmail', false));
});

it('narrows by who is pending or confirmed and by what was typed', function (): void {
    askedAndMaybeConfirmed($this, 'Ana Ruiz', 'portal');
    askedAndMaybeConfirmed($this, 'Beto Peña');
    askedAndMaybeConfirmed($this, 'Carla Soto');

    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'status' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 2)->where('filters.status', 'pending'));
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'status' => 'confirmed']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 1)->where('people.data.0.name', 'Ana Ruiz'));
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'status' => 'pending', 'q' => 'carla']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 1)->where('people.data.0.name', 'Carla Soto')->where('filters.q', 'carla'));
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'status' => 'everything']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 3)->where('filters.status', 'all'));
});

it('follows an older version when asked, and does not find one of another company or a draft', function (): void {
    $second = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $second->id, 'work_center_id' => $this->plant->id]);
    askedAndMaybeConfirmed($this, 'Ana Ruiz', 'portal', $this->policy);
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $draft = Document::factory()->draft()->policy()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->where('version.version', 2)->where('progress.confirmed', 0)->has('versions', 2));
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'version' => $this->policy->id]))->assertInertia(fn (Assert $page) => $page->where('version.version', 1)->where('progress.confirmed', 1));
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'version' => $foreign->id]))->assertNotFound();
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'version' => $draft->id]))->assertNotFound();
    $this->actingAs($this->hr)->get(route('tenant.disclosure.dissemination', [$this->tenant, 'version' => 'x']))->assertNotFound();
});

it('records paper acknowledgments from the form and answers on the field that is wrong', function (): void {
    $ana = askedAndMaybeConfirmed($this, 'Ana Ruiz');
    $beto = askedAndMaybeConfirmed($this, 'Beto Peña');
    $post = fn (array $body) => $this->actingAs($this->hr)->post(($this->url)('dissemination.acknowledgments.store', $this->policy->id), $body);

    $post(['employee_ids' => [$ana->id], 'date' => '2026-11-15', 'note' => 'Hoja 1'])->assertRedirect(($this->url)('dissemination'))->assertSessionHasNoErrors();
    expect(Acknowledgment::query()->where('employee_id', $ana->id)->sole()->channel)->toBe('manual');

    $post(['employee_ids' => [$beto->id], 'date' => '2026-11-21', 'note' => 'x'])->assertSessionHasErrors('date');
    $post(['employee_ids' => [$beto->id], 'date' => '2026-11-05', 'note' => 'x'])->assertSessionHasErrors('date');
    $post(['employee_ids' => [$beto->id], 'date' => '15/11/2026', 'note' => 'x'])->assertSessionHasErrors('date');
    $post(['employee_ids' => [], 'date' => '2026-11-15', 'note' => 'x'])->assertSessionHasErrors('employee_ids');
    $post(['employee_ids' => ['x'], 'date' => '2026-11-15', 'note' => 'x'])->assertSessionHasErrors('employee_ids.0');
    $post(['employee_ids' => [$ana->id], 'date' => '2026-11-15', 'note' => 'x'])->assertSessionHasErrors('employee_ids');
    $post(['employee_ids' => [$beto->id], 'date' => '2026-11-15', 'note' => str_repeat('a', 501)])->assertSessionHasErrors('note');

    expect(Acknowledgment::query()->where('employee_id', $beto->id)->sole()->acknowledged_at)->toBeNull();
});

it('records every pending person a search found, not only the ones ticked', function (): void {
    foreach (['Ana Ruiz', 'Ana Gil', 'Beto Peña'] as $name) {
        askedAndMaybeConfirmed($this, $name);
    }

    $this->actingAs($this->hr)->post(($this->url)('dissemination.acknowledgments.store', $this->policy->id), ['all_matching' => true, 'q' => 'ana', 'date' => '2026-11-15', 'note' => 'Hojas'])->assertSessionHasNoErrors();

    expect(Acknowledgment::query()->where('channel', 'manual')->count())->toBe(2)
        ->and(Acknowledgment::query()->whereNull('acknowledged_at')->count())->toBe(1);
});

it('lets only whoever manages record, and does not find the version of another company', function (): void {
    $ana = askedAndMaybeConfirmed($this, 'Ana Ruiz');
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $form = ['employee_ids' => [$ana->id], 'date' => '2026-11-15', 'note' => 'x'];

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(($this->url)('dissemination.acknowledgments.store', $this->policy->id), $form)->assertForbidden();
    $this->actingAs($this->hr)->post(($this->url)('dissemination.acknowledgments.store', $foreign->id), $form)->assertNotFound();
    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
});

it('reminds the pending people', function (): void {
    Queue::fake();
    askedAndMaybeConfirmed($this, 'Ana Ruiz');
    askedAndMaybeConfirmed($this, 'Beto Peña', 'portal');

    $this->actingAs($this->hr)->post(($this->url)('dissemination.reminders.store', $this->policy->id))->assertRedirect(($this->url)('dissemination'));

    Queue::assertPushed(SendPolicyReminder::class, 1);
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(($this->url)('dissemination.reminders.store', $this->policy->id))->assertForbidden();
});
