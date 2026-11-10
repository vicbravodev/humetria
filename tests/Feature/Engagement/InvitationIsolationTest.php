<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Invitation;
use App\Modules\Tenancy\Models\Tenant;

it('does not read or change the invitations of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $invitationOfB = Invitation::factory()->create(['tenant_id' => $b->id, 'survey_id' => App\Modules\Engagement\Models\Survey::factory()->active()->create(['tenant_id' => $b->id])->id]);

    workIn($a);
    expect(Invitation::query()->count())->toBe(0)
        ->and(Invitation::query()->find($invitationOfB->id))->toBeNull()
        ->and(Invitation::query()->whereKey($invitationOfB->id)->update(['answered' => true]))->toBe(0)
        ->and(Invitation::query()->whereKey($invitationOfB->id)->delete())->toBe(0);

    workIn($b);
    expect(Invitation::query()->sole()->answered)->toBeFalse();
});

it('ties an invitation to a person and a survey of its own company only', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $surveyOfB = App\Modules\Engagement\Models\Survey::factory()->active()->create(['tenant_id' => $b->id]);

    expect(fn () => Invitation::factory()->create(['tenant_id' => $a->id, 'survey_id' => $surveyOfB->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('invites a person once per survey and keeps no hour of marking it answered', function (): void {
    $invitation = Invitation::factory()->create();
    workIn(tenantOf($invitation->tenant_id));

    $invitation->update(['answered' => true]);

    expect(Illuminate\Support\Facades\Schema::hasColumn('engagement_invitations', 'updated_at'))->toBeFalse()
        ->and(Invitation::hashToken('abc'))->toBe(hash('sha256', 'abc'))
        ->and($invitation->surveyToken())->toBe(hash_hmac('sha256', "engagement-link:{$invitation->id}", config()->string('app.key')))
        ->and(fn () => Invitation::factory()->create(['tenant_id' => $invitation->tenant_id, 'survey_id' => $invitation->survey_id, 'employee_id' => $invitation->employee_id]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});
