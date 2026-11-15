<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\TenantHumiActivity;
use App\Modules\Tenancy\Models\Tenant;

mutates(TenantHumiActivity::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
});

it('says whether the person has asked Humi in this company', function (): void {
    $activity = resolve(HumiActivity::class);
    workIn($this->a);
    expect($activity->hasAsked($this->user->id))->toBeFalse();

    // Another person's use.
    $theirs = ConversationParticipant::factory()->for($this->other)->create(['tenant_id' => $this->a->id]);
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'participant_id' => $theirs->id]);
    expect($activity->hasAsked($this->user->id))->toBeFalse();

    // Use with no person (another module's record()).
    AiUsage::factory()->create(['tenant_id' => $this->a->id]);
    expect($activity->hasAsked($this->user->id))->toBeFalse();

    // The same person's use in another company.
    $inB = ConversationParticipant::factory()->for($this->user)->create(['tenant_id' => $this->b->id]);
    AiUsage::factory()->create(['tenant_id' => $this->b->id, 'participant_id' => $inB->id]);
    expect($activity->hasAsked($this->user->id))->toBeFalse();

    // Their own use here.
    $mine = ConversationParticipant::factory()->for($this->user)->create(['tenant_id' => $this->a->id]);
    AiUsage::factory()->create(['tenant_id' => $this->a->id, 'participant_id' => $mine->id]);
    expect($activity->hasAsked($this->user->id))->toBeTrue()
        ->and($activity->hasAsked($this->other->id))->toBeTrue();
});

it('answers with one query', function (): void {
    workIn($this->a);
    $activity = resolve(HumiActivity::class);

    $this->expectsDatabaseQueryCount(1);
    $activity->hasAsked($this->user->id);
});
