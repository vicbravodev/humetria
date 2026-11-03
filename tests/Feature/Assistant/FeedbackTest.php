<?php

declare(strict_types=1);

use App\Modules\Assistant\Actions\RateMessage;
use App\Modules\Assistant\Models\AiFeedback;
use App\Modules\Assistant\Models\ChatMessage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

mutates(RateMessage::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->hr = memberOf($this->a, TenantRole::Hr);
    $this->mine = ConversationParticipant::factory()->for($this->hr)->create(['tenant_id' => $this->a->id]);
    $this->conversation = assistantConversation($this->mine);
    $this->answerId = assistantAnswer($this->mine, $this->conversation->id);
});

function rate(string $messageId, array $body = [], ?object $as = null, string $slug = 'acme'): Illuminate\Testing\TestResponse
{
    return test()->actingAs($as ?? test()->hr)->postJson("/admin/{$slug}/assistant/feedback", ['message_id' => $messageId, 'rating' => 'up', ...$body]);
}

it('lives under the company of the panel', function (): void {
    expect(route('tenant.assistant.feedback', 'acme', absolute: false))->toBe('/admin/acme/assistant/feedback');
});

it('records a thumb up or down on an answer of Humi, with a reason from a closed list when it is down', function (): void {
    rate($this->answerId)->assertNoContent();
    $up = AiFeedback::query()->sole();

    expect($up->rating)->toBe('up')
        ->and($up->reason)->toBeNull()
        ->and($up->message_id)->toBe($this->answerId)
        ->and($up->conversation_id)->toBe($this->conversation->id)
        ->and($up->participant_id)->toBe($this->mine->id)
        ->and($up->tenant_id)->toBe($this->a->id);
});

it('replaces the rating of the same answer instead of adding another', function (): void {
    rate($this->answerId)->assertNoContent();
    rate($this->answerId, ['rating' => 'down', 'reason' => 'incorrect'])->assertNoContent();

    $rows = AiFeedback::query()->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->rating)->toBe('down')
        ->and($rows[0]->reason)->toBe('incorrect');
});

it('keeps no free text of the person: a comment sent is not kept', function (): void {
    rate($this->answerId, ['rating' => 'down', 'reason' => 'other', 'comment' => 'Juan Pérez me dio la peor calificación'])->assertNoContent();

    expect(Schema::getColumnListing('ai_feedback'))->toBe(['id', 'tenant_id', 'participant_id', 'conversation_id', 'message_id', 'rating', 'reason', 'created_at'])
        ->and(json_encode(AiFeedback::query()->sole()->toArray(), JSON_THROW_ON_ERROR))->not->toContain('Juan');
});

it('answers 404, the same, to a message of a colleague, of another company, of the person themself or that does not exist', function (): void {
    $colleague = ConversationParticipant::factory()->for(memberOf($this->a, TenantRole::Hr))->create(['tenant_id' => $this->a->id]);
    $theirs = assistantAnswer($colleague, assistantConversation($colleague)->id);
    $foreignParticipant = ConversationParticipant::factory()->create(['tenant_id' => $this->b->id]);
    $foreign = assistantAnswer($foreignParticipant, assistantConversation($foreignParticipant)->id);
    $ownQuestion = ChatMessage::query()->where('role', 'user')->where('participant_id', $this->mine->id)->sole();

    $answers = array_map(
        fn (string $id): string => rate($id)->assertNotFound()->getContent() ?: '',
        [$theirs, $foreign, $ownQuestion->id, (string) Str::uuid()],
    );

    expect(array_unique($answers))->toHaveCount(1)
        ->and(AiFeedback::query()->count())->toBe(0);
});

it('validates the rating, the reason and the id', function (array $body, string $field): void {
    rate($this->answerId, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'a rating that is neither' => [['rating' => 'meh'], 'rating'],
    'no rating' => [['rating' => null], 'rating'],
    'a reason outside the list' => [['rating' => 'down', 'reason' => 'me cae mal'], 'reason'],
    'an id that is no id' => [['message_id' => 'el último'], 'message_id'],
]);

it('is closed to whoever is not signed in, a member, or allowed to use Humi', function (): void {
    $this->postJson('/admin/acme/assistant/feedback', ['message_id' => $this->answerId, 'rating' => 'up'])->assertUnauthorized();
    rate($this->answerId, [], memberOf($this->b, TenantRole::Owner))->assertNotFound();
    rate($this->answerId, [], memberOf($this->a, TenantRole::Committee))->assertForbidden();
});

it('does not let a rating of one company reach another', function (): void {
    rate($this->answerId)->assertNoContent();
    workIn($this->b);

    expect(AiFeedback::query()->count())->toBe(0);
});
