<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\Acknowledge;
use App\Modules\Disclosure\Http\Controllers\PortalPolicyController;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\PolicyWorkerSection;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Acknowledge::class, PortalPolicyController::class, PolicyWorkerSection::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Acme Manufacturas S.A. de C.V.']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    workIn($this->tenant);
    $this->slug = asPerson($this->ana->id);
});

/** A published policy that reached the plant, asked of Ana. */
function policyAskedOfAna(int $version = 1): Document
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => test()->tenant->id, 'version' => $version, 'published_by' => test()->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'work_center_id' => test()->plant->id]);
    Acknowledgment::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'employee_id' => test()->ana->id]);

    return $policy;
}

it('lives at /me/{slug}/policy, in the person\'s pages, throttled like the rest of them', function (): void {
    expect(route('portal.policy.show', 'acme', absolute: false))->toBe('/me/acme/policy')
        ->and(route('portal.policy.acknowledge', 'acme', absolute: false))->toBe('/me/acme/policy/acknowledge')
        ->and(Route::getRoutes()->getByName('portal.policy.show')?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and(Route::getRoutes()->getByName('portal.policy.acknowledge')?->gatherMiddleware())->toContain('throttle:portal-actions')
        ->and(RateLimiter::limiter('portal-views'))->not->toBeNull()
        ->and(RateLimiter::limiter('portal-actions'))->not->toBeNull();
});

it('shows the person the policy in force in their center, with the company name in place, pending their confirmation', function (): void {
    policyAskedOfAna();

    $this->get(route('portal.policy.show', $this->slug))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure/portal-policy')
            ->where('company', 'Acme')
            ->where('name', 'Ana Ruiz')
            ->where('policy.version', 1)
            ->where('policy.sections.0.text', fn ($text) => str_contains($text, 'Acme Manufacturas S.A. de C.V.') && ! str_contains($text, '{legal_name}'))
            ->where('acknowledgment', fn ($ack) => $ack['state'] === 'pending' && $ack['on'] === null && isset($ack['documentId'])));
});

it('reads the policy without a proof, and takes the confirmation only after a recent proof that it is the person', function (EntryVia $via, int $minutes): void {
    $policy = policyAskedOfAna();
    enteredAs($this->ana->id, $via);
    $this->travel($minutes)->minutes();
    $page = route('portal.policy.show', $this->slug);

    $this->get($page)->assertOk();
    $this->from($page)->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertRedirect($page)->assertInertiaFlash('proofRequired.mode', 'code');
    expect(Acknowledgment::query()->sole()->acknowledged_at)->toBeNull();

    resolve(WorkerSessionStore::class)->prove();
    $this->from($page)->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertRedirect($page)->assertSessionHasNoErrors();
    expect(Acknowledgment::query()->sole()->acknowledged_at)->not->toBeNull();
})->with([
    'entered with a link' => [EntryVia::RequestedLink, 0],
    'proved 5 minutes ago' => [EntryVia::Key, 5],
]);

it('says when the person confirmed it', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    enteredAs($this->ana->id);
    $policy = policyAskedOfAna();

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertRedirect(route('portal.policy.show', $this->slug));

    $this->get(route('portal.policy.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page->where('acknowledgment.state', 'confirmed')->where('acknowledgment.on', '20 nov 2026'));
});

it('shows the version in force, not an older one the person was also asked', function (): void {
    policyAskedOfAna(1);
    $second = policyAskedOfAna(2);

    $this->get(route('portal.policy.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page->where('policy.version', 2)->where('acknowledgment.documentId', $second->id));
});

it('shows nothing to confirm when the center has no policy, or when the person was not asked', function (): void {
    $this->get(route('portal.policy.show', $this->slug))->assertInertia(fn (Assert $page) => $page->where('policy', null)->where('acknowledgment', null));

    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);

    $this->get(route('portal.policy.show', $this->slug))->assertInertia(fn (Assert $page) => $page->where('policy.version', 1)->where('acknowledgment', null));
});

it('hands the page only what is the person\'s own and the company\'s', function (): void {
    policyAskedOfAna();
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Beto Peña', 'email' => 'beto@acme.test']);

    $props = $this->get(route('portal.policy.show', $this->slug))->viewData('page')['props'];
    unset($props['errors']);
    $json = json_encode($props);

    expect($props)->toHaveKeys(['slug', 'company', 'name', 'policy', 'acknowledgment'])->not->toHaveKey('token')
        ->and($props['slug'])->toBe($this->tenant->slug)
        ->and($json)->not->toContain($other->id)->not->toContain('Beto')->not->toContain('beto@acme.test')->not->toContain($this->hr->email);
});

it('sends a visitor to enter, ends the page for a person who left, and confirms nothing for either', function (): void {
    $policy = policyAskedOfAna();
    session()->flush();

    $this->get(route('portal.policy.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));
    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertRedirect(route('worker.home', $this->slug));

    $slug = asPerson($this->ana->id);
    $this->ana->update(['deactivated_at' => now()]);

    $this->get(route('portal.policy.show', $slug))->assertRedirect(route('worker.home', $slug));
    $this->post(route('portal.policy.acknowledge', $slug), ['document' => $policy->id])->assertRedirect(route('worker.home', $slug));
    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
});

it('opens nothing of another company with this session, and answers 404 for a blocked company', function (): void {
    $policy = policyAskedOfAna();
    $other = Tenant::factory()->create();

    $this->get(route('portal.policy.show', $other->slug))->assertRedirect(route('worker.home', $other->slug));
    $this->post(route('portal.policy.acknowledge', $other->slug), ['document' => $policy->id])->assertRedirect(route('worker.home', $other->slug));

    Tenant::query()->whereKey($this->tenant->id)->update(suspension());
    $this->get(route('portal.policy.show', $this->slug))->assertNotFound();
    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertNotFound();
    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
});

it('confirms once, the version in force, and records the channel and the day without an address', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    enteredAs($this->ana->id);
    Log::spy();
    $policy = policyAskedOfAna();

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertSessionHasNoErrors();

    $ack = Acknowledgment::query()->sole();
    expect($ack->channel)->toBe('portal')
        ->and($ack->acknowledged_at?->toIso8601String())->toBe('2026-11-20T18:00:00+00:00')
        ->and($ack->note)->toBeNull()
        ->and(array_keys($ack->getAttributes()))->not->toContain('ip');
    Log::shouldHaveReceived('info')->with('disclosure.acknowledged', ['document_id' => $policy->id, 'employee_id' => $this->ana->id, 'channel' => 'portal'])->once();
});

it('refuses a second confirmation and changes nothing', function (): void {
    $policy = policyAskedOfAna();
    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id]);
    $first = Acknowledgment::query()->sole()->acknowledged_at;
    $this->travelTo(now()->addDay());
    enteredAs($this->ana->id);

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id])->assertSessionHasErrors('document');

    expect(Acknowledgment::query()->sole()->acknowledged_at)->toEqual($first);
});

it('refuses a version that is no longer in force and changes nothing', function (): void {
    $old = policyAskedOfAna(1);
    policyAskedOfAna(2);

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $old->id])->assertSessionHasErrors('document');

    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
});

it('does not find a request that was not made, a document of another company or a malformed id', function (): void {
    $unasked = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $unasked->id, 'work_center_id' => $this->plant->id]);
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    foreach ([$unasked->id, $foreign->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $id])->assertNotFound();
    }

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => 'x'])->assertSessionHasErrors('document');
    $this->post(route('portal.policy.acknowledge', $this->slug))->assertSessionHasErrors('document');
    expect(Acknowledgment::query()->count())->toBe(0);
});

it('does not let a person confirm for another', function (): void {
    $policy = policyAskedOfAna();
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $beto->id]);

    $this->post(route('portal.policy.acknowledge', $this->slug), ['document' => $policy->id]);

    expect(Acknowledgment::query()->where('employee_id', $beto->id)->sole()->acknowledged_at)->toBeNull()
        ->and(Acknowledgment::query()->where('employee_id', $this->ana->id)->sole()->acknowledged_at)->not->toBeNull();
});

it('tells the person, in their start, how many policies wait for their confirmation, at their page with no credential', function (): void {
    $ana = new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    $section = fn (): ?App\Modules\WorkerAccess\Data\WorkerSectionLink => resolve(PolicyWorkerSection::class)->for($ana);

    expect($section())->toBeNull();

    policyAskedOfAna();
    $link = $section();

    expect($link?->key)->toBe('policy')
        ->and($link?->label)->toBe('Política por confirmar')
        ->and($link?->href)->toBe(route('portal.policy.show', $this->tenant->slug))
        ->and($link?->pending)->toBe(1);

    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['seating'], Carbon\CarbonImmutable::now(config('app.display_timezone')));
    expect($section())->toBeNull();
});

it('counts in their start only the version in force at their center: an older version still unconfirmed is not a second pending', function (): void {
    $ana = new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    policyAskedOfAna(1);
    policyAskedOfAna(2);

    expect(resolve(PolicyWorkerSection::class)->for($ana)?->pending)->toBe(1);
});

it('tells nothing in their start about the policy of the center they left: the page shows none of it', function (): void {
    $ana = new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    $policy = policyAskedOfAna();
    $office = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana->update(['work_center_id' => $office->id]);

    expect(resolve(PolicyWorkerSection::class)->for($ana))->toBeNull();
    $this->get(route('portal.policy.show', $this->slug))->assertInertia(fn (Assert $page) => $page->where('policy', null)->where('acknowledgment', null));
    expect(fn () => resolve(Acknowledge::class)->handle($this->ana->id, $policy->id))->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Acknowledgment::query()->sole()->acknowledged_at)->toBeNull();
});

it('counts in their start the policy of the center they moved to, asked or not yet', function (): void {
    $ana = new App\Modules\WorkerAccess\Data\WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Ruiz');
    policyAskedOfAna();
    $office = WorkCenter::factory()->for($this->tenant)->create();
    $forOffice = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $forOffice->id, 'work_center_id' => $office->id]);
    $this->ana->update(['work_center_id' => $office->id]);

    expect(resolve(PolicyWorkerSection::class)->for($ana))->toBeNull();

    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $forOffice->id, 'employee_id' => $this->ana->id]);

    expect(resolve(PolicyWorkerSection::class)->for($ana)?->pending)->toBe(1);
});
