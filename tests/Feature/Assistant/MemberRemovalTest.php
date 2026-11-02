<?php

declare(strict_types=1);

use App\Modules\Assistant\Listeners\DeleteMemberConversations;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Actions\RemoveMember;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Models\Conversation;

mutates(DeleteMemberConversations::class);

it('deletes the conversations and the participant of a person removed from a company, and only theirs', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $owner = ownerOf($a);
    $member = memberOf($a, TenantRole::Hr);
    TenantMembership::factory()->for($b)->for($member)->role(TenantRole::Hr)->create();
    $other = memberOf($a, TenantRole::Viewer);

    $removed = ConversationParticipant::factory()->for($member)->create(['tenant_id' => $a->id]);
    $sameUserElsewhere = ConversationParticipant::factory()->for($member)->create(['tenant_id' => $b->id]);
    $colleague = ConversationParticipant::factory()->for($other)->create(['tenant_id' => $a->id]);
    $gone = assistantConversation($removed);
    $keptElsewhere = assistantConversation($sameUserElsewhere);
    $keptByColleague = assistantConversation($colleague);

    Log::spy();

    resolve(RemoveMember::class)->handle(TenantMembership::query()->where('tenant_id', $a->id)->where('user_id', $member->id)->sole(), $owner);

    Log::shouldHaveReceived('info')->with('assistant.member_conversations_deleted', ['tenant_id' => $a->id, 'user_id' => $member->id, 'conversations' => 1])->once();

    expect(Conversation::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and($gone->messages()->count())->toBe(0)
        ->and(Conversation::query()->whereKey($keptElsewhere->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($keptByColleague->id)->exists())->toBeTrue()
        ->and(ConversationParticipant::query()->withoutGlobalScopes()->whereKey($removed->id)->exists())->toBeFalse()
        ->and(ConversationParticipant::query()->withoutGlobalScopes()->whereKey($sameUserElsewhere->id)->exists())->toBeTrue()
        ->and(ConversationParticipant::query()->withoutGlobalScopes()->whereKey($colleague->id)->exists())->toBeTrue();
});

it('has nothing to delete for a person who never talked to Humi', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    $member = TenantMembership::factory()->for($tenant)->role(TenantRole::Viewer)->create();

    resolve(RemoveMember::class)->handle($member, $owner);

    expect(TenantMembership::query()->whereKey($member->id)->exists())->toBeFalse();
});
