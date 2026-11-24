<?php

declare(strict_types=1);

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->hr = memberOf($this->a, TenantRole::Hr);
    $this->mine = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->a->id]);
});

it('lives at /admin/{slug}/assistant', function (): void {
    expect(route('tenant.assistant.index', 'acme', absolute: false))->toBe('/admin/acme/assistant');
});

it('shows the history of the person, a page at a time, the newest first', function (): void {
    foreach (range(1, 16) as $number) {
        assistantConversation($this->mine, "Conversación {$number}", now()->startOfDay()->addMinutes($number));
    }
    $colleague = ConversationParticipant::factory()->for(memberOf($this->a, TenantRole::Hr))->create(['tenant_id' => $this->a->id]);
    assistantConversation($colleague, 'De mi colega');

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('assistant/index')
            ->where('conversations.total', 16)
            ->where('conversations.per_page', 15)
            ->has('conversations.data', 15)
            ->has('conversations.data.0', fn (Assert $row) => $row->hasAll(['id', 'title', 'updated_at'])->etc())
            ->where('conversations.data.0.title', 'Conversación 16'));

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a).'?page=2')
        ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));
});

it('sends no answer of anyone and nothing of what was said: only titles', function (): void {
    assistantConversation($this->mine, 'Avance');

    $props = json_encode($this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a))->viewData('page')['props'], JSON_THROW_ON_ERROR);

    expect($props)->not->toContain('"answers"')->not->toContain('"content"')->not->toContain('participant');
});

it('is empty for a person who never talked to Humi, and for the same person in another company', function (): void {
    $this->actingAs(memberOf($this->a, TenantRole::Viewer))->get(route('tenant.assistant.index', $this->a))
        ->assertInertia(fn (Assert $page) => $page->where('conversations.total', 0));
});

it('is closed like the rest of Humi: to a guest, a non member and the committee', function (): void {
    $this->get(route('tenant.assistant.index', $this->a))->assertRedirect('/admin/login');
    $this->actingAs(memberOf($this->b, TenantRole::Owner))->get(route('tenant.assistant.index', $this->a))->assertNotFound();
    $this->actingAs(memberOf($this->a, TenantRole::Committee))->get(route('tenant.assistant.index', $this->a))->assertForbidden();
});

it('searches the history on the server, ignoring case and accents, a page at a time and keeping the search', function (): void {
    foreach (range(1, 16) as $number) {
        assistantConversation($this->mine, "Riesgo por área {$number}");
    }
    assistantConversation($this->mine, 'Explícame la Ley Silla');

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a).'?q=RIESGO+AREA')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'RIESGO AREA')
            ->where('conversations.total', 16)
            ->has('conversations.data', 15)
            ->where('conversations.next_page_url', fn (?string $url): bool => str_contains((string) $url, 'q=RIESGO'))
        );

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a).'?q=silla')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.title', 'Explícame la Ley Silla'));

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a))
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', '')->where('conversations.total', 17));
});

it('finds only the conversations of the person in this company, never a colleague\'s nor another company\'s', function (): void {
    assistantConversation($this->mine, 'Avance de campañas');
    assistantConversation($this->mine, 'Explícame la Ley Silla');
    $colleague = ConversationParticipant::factory()->for(memberOf($this->a, TenantRole::Hr))->create(['tenant_id' => $this->a->id]);
    assistantConversation($colleague, 'Avance de mi colega');
    $elsewhere = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->b->id]);
    assistantConversation($elsewhere, 'Avance en otra empresa');

    $this->actingAs($this->hr)->get(route('tenant.assistant.index', $this->a).'?q=avance')
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.title', 'Avance de campañas'));

    // Searching from another company is the same 404 as any page of it.
    $this->actingAs(memberOf($this->b, TenantRole::Owner))->get(route('tenant.assistant.index', $this->a).'?q=avance')->assertNotFound();
});
