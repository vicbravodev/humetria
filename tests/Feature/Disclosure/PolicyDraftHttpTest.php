<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Http\Controllers\PolicyController;
use App\Modules\Disclosure\Http\Requests\DraftRequest;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PolicyController::class, DraftRequest::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->url = fn (string $name, mixed ...$parameters): string => route("tenant.disclosure.{$name}", [$this->tenant, ...$parameters]);
});

/** @return array{sections: list<array{key: string, text: string, items: list<string>}>} */
function policyForm(Document $draft, array $overrides = []): array
{
    return ['sections' => array_map(fn (array $section): array => ['key' => $section['key'], 'text' => $section['text'], 'items' => $section['items'], ...($overrides[$section['key']] ?? [])], $draft->content)];
}

it('shows the policy page without a draft, to whoever reads the disclosure', function (TenantRole $role, bool $manage): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(($this->url)('policy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('disclosure/policy')
            ->where('draft', null)
            ->where('can.manage', $manage)
            ->where('note', fn ($note) => str_starts_with($note, 'La política deberá indicar a los responsables')));
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
]);

it('closes the whole disclosure to the Comisión and to the handlers of complaints', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(($this->url)('policy'))->assertForbidden();
})->with([TenantRole::Committee, TenantRole::Complaints]);

it('sends a guest to sign in', function (): void {
    $this->get(($this->url)('policy'))->assertRedirect(route('login'));
});

it('starts the draft and shows its sections with what each allows', function (): void {
    $this->actingAs($this->hr)->post(($this->url)('policy.draft.store'))
        ->assertRedirect(($this->url)('policy'));

    $this->actingAs($this->hr)->get(($this->url)('policy'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draft.sections.0', fn ($section) => $section['key'] === 'commitments' && $section['heading'] === 'Compromisos' && $section['listStyle'] === 'numbers' && $section['removable'] === false && $section['required'] === false && count($section['items']) === 9)
            ->where('draft.sections.1', fn ($section) => $section['key'] === 'responsibles' && $section['required'] === true && $section['removable'] === false)
            ->where('draft.sections.4.removable', true)
            ->has('draft.sections', 12));

    expect(Document::query()->count())->toBe(1)
        ->and(Document::query()->sole()->created_by)->toBe($this->hr->id);
});

it('saves the draft from the form', function (): void {
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->put(($this->url)('policy.draft.update', $draft->id), policyForm($draft, ['responsibles' => ['text' => 'Dirección de RH']]))
        ->assertRedirect(($this->url)('policy'));

    expect(array_column($draft->fresh()?->content ?? [], 'text', 'key')['responsibles'])->toBe('Dirección de RH');
});

it('refuses a form without sections, with a malformed one or with a section that is not text', function (array $form, string $field): void {
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->put(($this->url)('policy.draft.update', $draft->id), $form)
        ->assertSessionHasErrors($field);
})->with([
    'none' => [[], 'sections'],
    'not a list' => [['sections' => 'x'], 'sections'],
    'a section without a key' => [['sections' => [['text' => 'x', 'items' => []]]], 'sections.0.key'],
    'text that is not text' => [['sections' => [['key' => 'commitments', 'text' => ['a'], 'items' => []]]], 'sections.0.text'],
    'items that are not a list' => [['sections' => [['key' => 'commitments', 'text' => 'x', 'items' => 'a']]], 'sections.0.items'],
    'an item that is too long' => [['sections' => [['key' => 'commitments', 'text' => 'x', 'items' => [str_repeat('a', 1001)]]]], 'sections.0.items.0'],
]);

it('answers the business rules of the draft on the sections field', function (): void {
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->put(($this->url)('policy.draft.update', $draft->id), ['sections' => [['key' => 'commitments', 'text' => 'x', 'items' => []]]])
        ->assertSessionHasErrors('sections');
});

it('discards the draft', function (): void {
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle($this->hr->id);

    $this->actingAs($this->hr)->delete(($this->url)('policy.draft.destroy', $draft->id))
        ->assertRedirect(($this->url)('policy'));

    expect(Document::query()->count())->toBe(0);
});

it('lets the viewer read but not write', function (): void {
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle($this->hr->id);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->post(($this->url)('policy.draft.store'))->assertForbidden();
    $this->actingAs($viewer)->put(($this->url)('policy.draft.update', $draft->id), policyForm($draft))->assertForbidden();
    $this->actingAs($viewer)->delete(($this->url)('policy.draft.destroy', $draft->id))->assertForbidden();

    expect(Document::query()->count())->toBe(1);
});

it('does not find the draft of another company: the same 404 as one that does not exist', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Document::factory()->draft()->policy()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);
    $own = resolve(StartPolicyDraft::class)->handle($this->hr->id);

    foreach ([$foreign->id, '0194c1de-0000-7000-8000-000000000000'] as $id) {
        $this->actingAs($this->hr)->put(($this->url)('policy.draft.update', $id), policyForm($own))->assertNotFound();
        $this->actingAs($this->hr)->delete(($this->url)('policy.draft.destroy', $id))->assertNotFound();
    }

    $this->actingAs($this->hr)->put(($this->url)('policy.draft.update', 'not-a-uuid'), policyForm($own))->assertNotFound();
    expect(Document::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('shows the draft of its own company only', function (): void {
    $other = Tenant::factory()->create();
    Document::factory()->draft()->policy()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->get(($this->url)('policy'))->assertInertia(fn (Assert $page) => $page->where('draft', null));
});

/** The form that publishes: the centers (all by default), the incisos and the review. @return array<string, mixed> */
function publishForm(array $overrides = []): array
{
    return ['all_centers' => true, 'covers' => ['a', 'b', 'c'], 'reviewed' => true, ...$overrides];
}

/** A complete draft of the company at hand. */
function completePolicyDraft(?Document $draft = null): Document
{
    $draft ??= resolve(StartPolicyDraft::class)->handle(null);

    return resolve(App\Modules\Disclosure\Actions\SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => [
        'key' => $section['key'],
        'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'Dirección de RH' : $section['text'],
        'items' => $section['items'],
    ], $draft->content));
}

it('publishes the draft for every center in use from the form', function (): void {
    $center = App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = completePolicyDraft();

    $this->actingAs($this->hr)->post(($this->url)('policy.publish', $draft->id), publishForm())
        ->assertRedirect(($this->url)('policy'))
        ->assertSessionHasNoErrors();

    $published = $draft->fresh();
    expect($published?->version)->toBe(1)
        ->and($published?->status->value)->toBe('published')
        ->and(App\Modules\Disclosure\Models\DocumentCenter::query()->pluck('work_center_id')->all())->toBe([$center->id]);
});

it('publishes for the centers named when not all', function (): void {
    $north = App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = completePolicyDraft();

    $this->actingAs($this->hr)->post(($this->url)('policy.publish', $draft->id), publishForm(['all_centers' => false, 'work_center_ids' => [$north->id], 'covers' => ['a', 'b']]))
        ->assertSessionHasNoErrors();

    expect(App\Modules\Disclosure\Models\DocumentCenter::query()->pluck('work_center_id')->all())->toBe([$north->id])
        ->and($draft->fresh()?->covers)->toBe(['a', 'b']);
});

it('answers what is wrong with a publication on the field it belongs to', function (array $form, string $field): void {
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = completePolicyDraft();

    $this->actingAs($this->hr)->post(($this->url)('policy.publish', $draft->id), publishForm($form))->assertSessionHasErrors($field);

    expect($draft->fresh()?->version)->toBeNull();
})->with([
    'without the review' => [['reviewed' => false], 'reviewed'],
    'no review at all' => [['reviewed' => null], 'reviewed'],
    'no centers chosen' => [['all_centers' => false, 'work_center_ids' => []], 'work_center_ids'],
    'a center that is not a uuid' => [['all_centers' => false, 'work_center_ids' => ['x']], 'work_center_ids.0'],
    'an inciso that does not exist' => [['covers' => ['a', 'z']], 'covers.1'],
    'covers that is not a list' => [['covers' => 'a'], 'covers'],
]);

it('answers a policy without responsibles on that field', function (): void {
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = resolve(StartPolicyDraft::class)->handle(null);

    $this->actingAs($this->hr)->post(($this->url)('policy.publish', $draft->id), publishForm())->assertSessionHasErrors(['responsibles', 'resources']);
});

it('lets the viewer read but not publish, and does not find a draft of another company', function (): void {
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = completePolicyDraft();
    $foreign = Document::factory()->draft()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(($this->url)('policy.publish', $draft->id), publishForm())->assertForbidden();
    $this->actingAs($this->hr)->post(($this->url)('policy.publish', $foreign->id), publishForm())->assertNotFound();

    expect($draft->fresh()?->version)->toBeNull();
});

it('shows the version in force with its words, the company name in place, and the history a page at a time', function (): void {
    $this->tenant->update(['legal_name' => 'Manufacturas del Bajío S.A. de C.V.']);
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $first = completePolicyDraft();
    resolve(App\Modules\Disclosure\Actions\PublishPolicy::class)->handle($first->id, null, ['a', 'b', 'c'], true, $this->hr->id);

    foreach (range(2, 7) as $ignored) {
        $next = completePolicyDraft();
        resolve(App\Modules\Disclosure\Actions\PublishPolicy::class)->handle($next->id, null, ['a'], true, $this->hr->id);
    }

    $this->actingAs($this->hr)->get(($this->url)('policy'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('latest.version', 7)
            ->where('latest.covers', ['a'])
            ->where('latest.centers', 1)
            ->where('latest.sections.0.text', fn ($text) => str_starts_with($text, 'En este centro de trabajo Manufacturas del Bajío S.A. de C.V. en relación') && ! str_contains($text, '{legal_name}'))
            ->where('history.total', 7)
            ->where('history.per_page', 5)
            ->where('history.data.0', fn ($row) => $row['version'] === 7 && $row['centers'] === 1 && $row['covers'] === ['a'] && isset($row['publishedOn']) && isset($row['id']))
            ->has('history.data', 5)
            ->where('centersInUse', 1));

    $this->actingAs($this->hr)->get(route('tenant.disclosure.policy', [$this->tenant, 'versions' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('history.data', 2)->where('history.data.0.version', 2));
});

it('opens a published version to read it, and nothing else', function (): void {
    App\Modules\Tenancy\Models\WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $draft = completePolicyDraft();
    $published = resolve(App\Modules\Disclosure\Actions\PublishPolicy::class)->handle($draft->id, null, ['a', 'b', 'c'], true, $this->hr->id);
    $open = resolve(StartPolicyDraft::class)->handle(null);
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(($this->url)('policy.version', $published->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('disclosure/policy-version')->where('version.version', 1)->where('version.sections.1.text', 'Dirección de RH')->has('version.sections', 12));

    $this->actingAs($this->hr)->get(($this->url)('policy.version', $open->id))->assertNotFound();
    $this->actingAs($this->hr)->get(($this->url)('policy.version', $foreign->id))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(($this->url)('policy.version', $published->id))->assertForbidden();
});
