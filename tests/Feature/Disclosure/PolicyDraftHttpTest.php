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
