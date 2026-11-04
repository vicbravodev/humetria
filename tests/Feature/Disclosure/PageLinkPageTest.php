<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Http\Controllers\PageLinkController;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PageLinkController::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
    $this->url = fn (string $name = 'page'): string => route("tenant.disclosure.{$name}", $this->tenant);
});

it('is for whoever manages the disclosure', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(($this->url)())->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
    'complaints' => [TenantRole::Complaints, 403],
]);

it('offers to create the link when there is none, without creating it by looking', function (): void {
    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->component('disclosure/page')->where('link', null));

    expect(PageLink::query()->count())->toBe(0)->and(AuditEntry::query()->where('event', 'disclosure.page_link_viewed')->count())->toBe(0);
});

it('shows the link, its state and its dates, and audits that someone saw it, without the token', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    resolve(App\Modules\Disclosure\Actions\ChangePageLinkExpiry::class)->handle($this->hr->id, '2027-01-15');
    $token = (string) $link->token();

    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page
        ->where('link.state', ['value' => 'active', 'label' => 'Activo'])
        ->where('link.url', url("workers/{$token}"))
        ->where('link.version', 1)
        ->where('link.createdOn', '20 nov 2026')
        ->where('link.rotatedOn', null)
        ->where('link.expiresOn', '15 ene 2027')
        ->where('link.needsRotation', false));

    $entry = AuditEntry::query()->where('event', 'disclosure.page_link_viewed')->sole();
    expect($entry->subject_id)->toBe($link->id)->and(json_encode($entry->metadata))->not->toContain($token);
});

it('says the link is switched off or expired', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $link->update(['disabled_at' => now()]);
    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->where('link.state.value', 'disabled'));

    $link->update(['disabled_at' => null, 'expires_at' => now()->addMinute()]);
    $this->travelTo(now()->addMinutes(2));
    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->where('link.state.value', 'expired'));
});

it('asks to rotate a link whose app key was dropped', function (): void {
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $link->update(['token_hash' => hash('sha256', 'from a key that is gone')]);

    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->where('link.url', null)->where('link.needsRotation', true));
});

it('creates, rotates, switches off and on, and sets the expiry from the page', function (): void {
    $this->actingAs($this->hr)->post(($this->url)('page.issue'))->assertRedirect(($this->url)());
    $first = (string) PageLink::query()->sole()->token();

    $this->actingAs($this->hr)->post(($this->url)('page.rotate'))->assertRedirect(($this->url)());
    expect(PageLink::query()->sole()->version)->toBe(2)->and(PageLink::query()->sole()->token())->not->toBe($first);

    $this->actingAs($this->hr)->post(($this->url)('page.disable'))->assertRedirect(($this->url)());
    expect(PageLink::query()->sole()->status()->value)->toBe('disabled');

    $this->actingAs($this->hr)->post(($this->url)('page.enable'))->assertRedirect(($this->url)());
    expect(PageLink::query()->sole()->status()->value)->toBe('active');

    $this->actingAs($this->hr)->put(($this->url)('page.expiry'), ['expires_on' => '2027-01-15'])->assertRedirect(($this->url)())->assertSessionHasNoErrors();
    expect(PageLink::query()->sole()->expires_at)->not->toBeNull();

    $this->actingAs($this->hr)->put(($this->url)('page.expiry'), ['expires_on' => null])->assertSessionHasNoErrors();
    expect(PageLink::query()->sole()->expires_at)->toBeNull();
});

it('answers a bad expiry on its field', function (array $body): void {
    resolve(IssuePageLink::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->put(($this->url)('page.expiry'), $body)->assertSessionHasErrors('expires_on');
})->with([
    'today' => [['expires_on' => '2026-11-20']],
    'too far' => [['expires_on' => '2029-11-21']],
    'not a day' => [['expires_on' => '20/11/2027']],
]);

it('lets only whoever manages change the link', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    foreach (['page.issue', 'page.rotate', 'page.disable', 'page.enable'] as $name) {
        $this->actingAs($user)->post(($this->url)($name))->assertForbidden();
    }
    $this->actingAs($user)->put(($this->url)('page.expiry'), ['expires_on' => null])->assertForbidden();

    expect(PageLink::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('does not show the link of another company', function (): void {
    $other = Tenant::factory()->create();
    PageLink::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->get(($this->url)())->assertInertia(fn (Assert $page) => $page->where('link', null));
});

it('puts the page in front of the people on the board while the link is active, and takes it away when it is off, expired or none', function (Closure $arrange, string $state): void {
    $center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);
    $arrange($this);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.requirements', fn ($rows) => collect($rows)->firstWhere('code', 'D3')['state']['value'] === $state));
})->with([
    'no link' => [fn () => null, 'missing'],
    'an active link' => [fn ($test) => resolve(IssuePageLink::class)->handle($test->hr->id), 'met'],
    'a switched off link' => [fn ($test) => resolve(IssuePageLink::class)->handle($test->hr->id)->update(['disabled_at' => now()]), 'missing'],
    'an expired link' => [fn ($test) => resolve(IssuePageLink::class)->handle($test->hr->id)->update(['expires_at' => now()->addSecond()]) && test()->travelTo(now()->addSeconds(2)), 'missing'],
    'an expired link but a poster' => [function ($test): void {
        resolve(IssuePageLink::class)->handle($test->hr->id)->update(['disabled_at' => now()]);
        DisseminationRecord::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => WorkCenter::query()->firstOrFail()->id]);
    }, 'met'],
]);

it('warns on the board 30 days before the link expires, and not 31', function (int $days, bool $warned): void {
    $center = WorkCenter::factory()->for($this->tenant)->create();
    $link = resolve(IssuePageLink::class)->handle($this->hr->id);
    $link->update(['expires_at' => now()->addDays($days)]);

    $this->actingAs($this->hr)->get(route('tenant.disclosure.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0.requirements', function ($rows) use ($warned): bool {
            $row = collect($rows)->firstWhere('code', 'D10');

            return $warned ? ($row['state']['value'] === 'notice' && str_contains($row['detail'], 'vence el')) : $row === null;
        }));
    expect($center->id)->not->toBeEmpty();
})->with([
    'in 30 days' => [30, true],
    'in 31 days' => [31, false],
    'tomorrow' => [1, true],
]);
