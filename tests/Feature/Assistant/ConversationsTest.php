<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Actions\DeleteConversation;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\Conversations;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;

mutates(Conversations::class, DeleteConversation::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->hr = memberOf($this->a, TenantRole::Hr);
    $this->mine = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->a->id]);
    $this->colleague = memberOf($this->a, TenantRole::Hr);
    $this->colleagues = ConversationParticipant::factory()->for($this->colleague)->create(['tenant_id' => $this->a->id]);
});

function conversationsUrl(Tenant $tenant, string ...$more): string
{
    return "/admin/{$tenant->slug}/assistant/conversations".($more === [] ? '' : '/'.implode('/', $more));
}

it('lives under the company of the panel', function (): void {
    expect(route('tenant.assistant.conversations.index', 'acme', absolute: false))->toBe('/admin/acme/assistant/conversations');
});

it('lists the conversations of the person, the newest first, a page at a time', function (): void {
    $old = assistantConversation($this->mine, 'Antigua');
    $old->forceFill(['updated_at' => now()->subDay()])->save();
    $new = assistantConversation($this->mine, 'Reciente');

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('per_page', 15)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.0.title', 'Reciente')
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonMissingPath('data.0.participant_id');
});

it('pages the list in the server', function (): void {
    foreach (range(1, 16) as $number) {
        assistantConversation($this->mine, "Conversación {$number}");
    }

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a))->assertJsonCount(15, 'data')->assertJsonPath('last_page', 2);
    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a).'?page=2')->assertJsonCount(1, 'data');
});

it('never lists the conversations of a colleague, nor those of the same person in another company', function (): void {
    assistantConversation($this->colleagues, 'De mi colega');
    TenantMembership::factory()->for($this->b)->for($this->hr)->role(TenantRole::Hr)->create();
    $inB = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->b->id]);
    assistantConversation($inB, 'En Beta');
    $own = assistantConversation($this->mine, 'Mía');

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a))->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $own->id);
    $this->actingAs($this->hr)->getJson(conversationsUrl($this->b))->assertJsonPath('total', 1)->assertJsonPath('data.0.title', 'En Beta');
});

it('shows the messages of an own conversation, the latest first, with a cursor for the older ones', function (): void {
    $conversation = assistantConversation($this->mine, 'Avance');

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a, $conversation->id))
        ->assertOk()
        ->assertJsonPath('id', $conversation->id)
        ->assertJsonPath('title', 'Avance')
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.0.content', 'Hola')
        ->assertJsonPath('next_cursor', null)
        ->assertJsonMissingPath('messages.0.participant_id');
});

it('answers 404, the same, to a conversation of a colleague, of another company or that does not exist', function (): void {
    $colleagues = assistantConversation($this->colleagues);
    $inB = ConversationParticipant::factory()->create(['tenant_id' => $this->b->id]);
    $foreign = assistantConversation($inB);
    $missing = (string) Str::uuid();

    $answers = array_map(
        fn (string $id): string => $this->actingAs($this->hr)->getJson(conversationsUrl($this->a, $id))->assertNotFound()->getContent() ?: '',
        [$colleagues->id, $foreign->id, $missing],
    );

    expect(array_unique($answers))->toHaveCount(1);
});

it('does not let the same person read the history they have in another company through this one', function (): void {
    TenantMembership::factory()->for($this->b)->for($this->hr)->role(TenantRole::Hr)->create();
    $inB = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->b->id]);
    $conversationInB = assistantConversation($inB, 'En Beta');

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a, $conversationInB->id))->assertNotFound();
    $this->actingAs($this->hr)->getJson(conversationsUrl($this->b, $conversationInB->id))->assertOk();
});

it('deletes an own conversation with its messages', function (): void {
    $conversation = assistantConversation($this->mine);

    $this->actingAs($this->hr)->deleteJson(conversationsUrl($this->a, $conversation->id))->assertNoContent();

    expect(Conversation::query()->whereKey($conversation->id)->exists())->toBeFalse()
        ->and($conversation->messages()->count())->toBe(0);
});

it('does not delete, and answers 404 to, the conversation of a colleague or another company', function (): void {
    $colleagues = assistantConversation($this->colleagues);
    $foreign = assistantConversation(ConversationParticipant::factory()->create(['tenant_id' => $this->b->id]));

    $this->actingAs($this->hr)->deleteJson(conversationsUrl($this->a, $colleagues->id))->assertNotFound();
    $this->actingAs($this->hr)->deleteJson(conversationsUrl($this->a, $foreign->id))->assertNotFound();

    expect(Conversation::query()->whereKey($colleagues->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('is closed to whoever is not a signed-in member of the company', function (): void {
    $this->getJson(conversationsUrl($this->a))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson(conversationsUrl($this->a))->assertNotFound();
    $this->actingAs(memberOf($this->b, TenantRole::Owner))->getJson(conversationsUrl($this->a))->assertNotFound();
});

it('serves the viewer and refuses the committee', function (): void {
    $this->actingAs(memberOf($this->a, TenantRole::Viewer))->getJson(conversationsUrl($this->a))->assertOk();
    $this->actingAs(memberOf($this->a, TenantRole::Committee))->getJson(conversationsUrl($this->a))->assertForbidden();
});

it('is closed while a superadmin impersonates the owner, whose conversations are not theirs', function (): void {
    $owner = ownerOf($this->a);
    $this->actingAs(superadmin())->post(route('companies.impersonate', $this->a));

    $this->getJson(conversationsUrl($this->a))->assertNotFound();
});

it('belongs to no one when the participant is gone: the conversation is not found', function (): void {
    $conversation = assistantConversation($this->mine);
    $this->mine->delete();
    resolve(Conversations::class);

    $this->actingAs($this->hr)->getJson(conversationsUrl($this->a, $conversation->id))->assertNotFound();
});
