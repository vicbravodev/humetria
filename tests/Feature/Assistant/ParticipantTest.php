<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Actions\ResolveParticipant;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

mutates(ResolveParticipant::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->user = User::factory()->create();
});

it('gives a person one participant in a company, the same one every time', function (): void {
    workIn($this->a);

    $first = resolve(ResolveParticipant::class)->handle($this->user->id);
    $again = resolve(ResolveParticipant::class)->handle($this->user->id);

    expect($again->id)->toBe($first->id)
        ->and($first->tenant_id)->toBe($this->a->id)
        ->and($first->user_id)->toBe($this->user->id)
        ->and(ConversationParticipant::query()->count())->toBe(1);
});

it('gives the same person a different participant in each company, so their histories stay apart', function (): void {
    workIn($this->a);
    $inA = resolve(ResolveParticipant::class)->handle($this->user->id);
    workIn($this->b);
    $inB = resolve(ResolveParticipant::class)->handle($this->user->id);

    expect($inB->id)->not->toBe($inA->id)
        ->and($inB->tenant_id)->toBe($this->b->id);
});

it('does not let one company read or change the participants of another', function (): void {
    $other = ConversationParticipant::factory()->create(['tenant_id' => $this->b->id]);
    workIn($this->a);
    ConversationParticipant::factory()->create(['tenant_id' => $this->a->id]);

    expect(ConversationParticipant::query()->count())->toBe(1)
        ->and(ConversationParticipant::query()->find($other->id))->toBeNull()
        ->and(ConversationParticipant::query()->whereKey($other->id)->update(['user_id' => $this->user->id]))->toBe(0);
});

it('stores the participant under the alias the SDK records for a conversation', function (): void {
    expect((new ConversationParticipant)->getMorphClass())->toBe('assistant_participant');
});

it('refuses a second participant for the same person in the same company', function (): void {
    ConversationParticipant::factory()->for($this->user)->create(['tenant_id' => $this->a->id]);

    expect(fn () => ConversationParticipant::factory()->for($this->user)->create(['tenant_id' => $this->a->id]))
        ->toThrow(QueryException::class);
});

it('logs the first time a person talks to Humi in a company, and only then', function (): void {
    workIn($this->a);
    Log::spy();

    resolve(ResolveParticipant::class)->handle($this->user->id);
    resolve(ResolveParticipant::class)->handle($this->user->id);

    Log::shouldHaveReceived('info')->once()->with('assistant.participant_created', ['tenant_id' => $this->a->id, 'user_id' => $this->user->id]);
});
