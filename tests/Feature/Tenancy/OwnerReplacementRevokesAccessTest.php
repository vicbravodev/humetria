<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\UnassignedCases;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use Database\Factories\Complaints\ComplaintAssignmentFactory;
use Database\Factories\Complaints\ComplaintFactory;
use Laravel\Ai\Models\Conversation;

it('ends what the previous owner held when a new owner replaces them, like removing a member does', function (): void {
    $tenant = Tenant::factory()->create();
    $previous = ownerOf($tenant);
    $next = User::factory()->create();

    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $previous->id]);
    $complaint = ComplaintFactory::new()->create(['tenant_id' => $tenant->id]);
    ComplaintAssignmentFactory::new()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $previous->id]);
    $participant = ConversationParticipant::factory()->for($previous)->create(['tenant_id' => $tenant->id]);
    $conversation = assistantConversation($participant);

    resolve(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->role(TenantRole::Owner)->create(['email' => $next->email]), $next);

    resolve(Companies::class)->enter($tenant->id);

    expect(ComplaintHandler::query()->where('user_id', $previous->id)->whereNull('ended_at')->count())->toBe(0)
        ->and(ComplaintAssignment::query()->where('user_id', $previous->id)->whereNull('ended_on')->count())->toBe(0)
        ->and(ConversationParticipant::query()->whereKey($participant->id)->exists())->toBeFalse()
        ->and(Conversation::query()->whereKey($conversation->id)->exists())->toBeFalse()
        ->and(resolve(UnassignedCases::class)->count())->toBe(1);
});
