<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\Acknowledge;
use App\Modules\Disclosure\Http\Controllers\PortalPolicyController;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Acknowledge::class, PortalPolicyController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'legal_name' => 'Acme Manufacturas S.A. de C.V.']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    workIn($this->tenant);
    $this->token = resolve(PortalAccess::class)->token($this->ana->id);
});

/** A published policy that reached the plant, asked of Ana. */
function policyAskedOfAna(int $version = 1): Document
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => test()->tenant->id, 'version' => $version, 'published_by' => test()->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'work_center_id' => test()->plant->id]);
    Acknowledgment::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'employee_id' => test()->ana->id]);

    return $policy;
}

it('lives at /portal/{token}/policy, next to the rest of the personal page, throttled like it', function (): void {
    expect(route('portal.policy.show', 'abc', absolute: false))->toBe('/portal/abc/policy')
        ->and(route('portal.policy.acknowledge', 'abc', absolute: false))->toBe('/portal/abc/policy/acknowledge')
        ->and(Route::getRoutes()->getByName('portal.policy.show')?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and(Route::getRoutes()->getByName('portal.policy.acknowledge')?->gatherMiddleware())->toContain('throttle:portal-actions')
        ->and(RateLimiter::limiter('portal-views'))->not->toBeNull()
        ->and(RateLimiter::limiter('portal-actions'))->not->toBeNull();
});

it('shows the person the policy in force in their center, with the company name in place, pending their confirmation', function (): void {
    policyAskedOfAna();

    $this->get(route('portal.policy.show', $this->token))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure/portal-policy')
            ->where('company', 'Acme')
            ->where('name', 'Ana Ruiz')
            ->where('policy.version', 1)
            ->where('policy.sections.0.text', fn ($text) => str_contains($text, 'Acme Manufacturas S.A. de C.V.') && ! str_contains($text, '{legal_name}'))
            ->where('acknowledgment', fn ($ack) => $ack['state'] === 'pending' && $ack['on'] === null && isset($ack['documentId'])));
});

it('says when the person confirmed it', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $policy = policyAskedOfAna();

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $policy->id])->assertRedirect(route('portal.policy.show', $this->token));

    $this->get(route('portal.policy.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('acknowledgment.state', 'confirmed')->where('acknowledgment.on', '20 nov 2026'));
});

it('shows the version in force, not an older one the person was also asked', function (): void {
    policyAskedOfAna(1);
    $second = policyAskedOfAna(2);

    $this->get(route('portal.policy.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('policy.version', 2)->where('acknowledgment.documentId', $second->id));
});

it('shows nothing to confirm when the center has no policy, or when the person was not asked', function (): void {
    $this->get(route('portal.policy.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('policy', null)->where('acknowledgment', null));

    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $this->plant->id]);

    $this->get(route('portal.policy.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('policy.version', 1)->where('acknowledgment', null));
});

it('hands the page only what is the person\'s own and the company\'s', function (): void {
    policyAskedOfAna();
    $other = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Beto Peña', 'email' => 'beto@acme.test']);

    $props = $this->get(route('portal.policy.show', $this->token))->viewData('page')['props'];
    unset($props['errors']);
    $json = json_encode($props);

    expect($props)->toHaveKeys(['token', 'company', 'name', 'policy', 'acknowledgment'])
        ->and($props['token'])->toBe($this->token)
        ->and($json)->not->toContain($other->id)->not->toContain('Beto')->not->toContain('beto@acme.test')->not->toContain($this->hr->email);
});

it('is a 404 that says nothing for a link that is not valid, from another company, replaced, of a person who left or of a blocked company', function (Closure $break): void {
    policyAskedOfAna();
    $token = $break($this);

    $this->get(route('portal.policy.show', $token))->assertNotFound();
    $this->post(route('portal.policy.acknowledge', $token), ['document' => Document::query()->firstOrFail()->id])->assertNotFound();
    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
})->with([
    'made up' => [fn () => str_repeat('a', 43)],
    'replaced' => [function ($test) {
        $old = $test->token;
        resolve(App\Modules\Telework\Actions\IssuePortalLink::class)->handle($test->ana->id, revoke: true);

        return $old;
    }],
    'of a person who left' => [function ($test) {
        $test->ana->update(['deactivated_at' => now()]);

        return $test->token;
    }],
    'of a blocked company' => [function ($test) {
        Tenant::query()->whereKey($test->tenant->id)->update(['deactivated_at' => now()]);

        return $test->token;
    }],
]);

it('confirms once, the version in force, and records the channel and the day without an address', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    Log::spy();
    $policy = policyAskedOfAna();

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $policy->id])->assertSessionHasNoErrors();

    $ack = Acknowledgment::query()->sole();
    expect($ack->channel)->toBe('portal')
        ->and($ack->acknowledged_at?->toIso8601String())->toBe('2026-11-20T18:00:00+00:00')
        ->and($ack->note)->toBeNull()
        ->and(array_keys($ack->getAttributes()))->not->toContain('ip');
    Log::shouldHaveReceived('info')->with('disclosure.acknowledged', ['document_id' => $policy->id, 'employee_id' => $this->ana->id, 'channel' => 'portal'])->once();
});

it('refuses a second confirmation and changes nothing', function (): void {
    $policy = policyAskedOfAna();
    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $policy->id]);
    $first = Acknowledgment::query()->sole()->acknowledged_at;
    $this->travelTo(now()->addDay());

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $policy->id])->assertSessionHasErrors('document');

    expect(Acknowledgment::query()->sole()->acknowledged_at)->toEqual($first);
});

it('refuses a version that is no longer in force and changes nothing', function (): void {
    $old = policyAskedOfAna(1);
    policyAskedOfAna(2);

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $old->id])->assertSessionHasErrors('document');

    expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
});

it('does not find a request that was not made, a document of another company or a malformed id', function (): void {
    $unasked = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $unasked->id, 'work_center_id' => $this->plant->id]);
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    foreach ([$unasked->id, $foreign->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $id])->assertNotFound();
    }

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => 'x'])->assertSessionHasErrors('document');
    $this->post(route('portal.policy.acknowledge', $this->token))->assertSessionHasErrors('document');
    expect(Acknowledgment::query()->count())->toBe(0);
});

it('does not let a person confirm for another', function (): void {
    $policy = policyAskedOfAna();
    $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $beto->id]);

    $this->post(route('portal.policy.acknowledge', $this->token), ['document' => $policy->id]);

    expect(Acknowledgment::query()->where('employee_id', $beto->id)->sole()->acknowledged_at)->toBeNull()
        ->and(Acknowledgment::query()->where('employee_id', $this->ana->id)->sole()->acknowledged_at)->not->toBeNull();
});
