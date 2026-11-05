<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Complaints\Actions\ActivateChannel;
use App\Modules\Complaints\Actions\DesignateHandler;
use App\Modules\Complaints\Actions\EndHandler;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Listeners\EndHandlerOnMemberRemoval;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Notifications\HandlersChanged;
use App\Modules\Complaints\Notifications\YouWereDesignated;
use App\Modules\Complaints\Support\HandlerCoverage;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\MemberRemoved;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DesignateHandler::class, EndHandler::class, HandlerCoverage::class, EndHandlerOnMemberRemoval::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Planta Querétaro']);
});

it('lists as complaint handlers the members whose role can handle complaints, by name', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $committee = memberOf($this->tenant, TenantRole::Committee);
    $outsider = memberOf($this->tenant, TenantRole::Complaints);
    memberOf(Tenant::factory()->create(), TenantRole::Hr);
    workIn($this->tenant);

    $ids = array_map(fn ($member): int => $member->userId, resolve(Members::class)->complaintHandlers());

    expect($ids)->toContain($this->owner->id, $this->hr->id, $committee->id, $outsider->id)
        ->and($ids)->not->toContain($viewer->id)
        ->and($ids)->toHaveCount(4);
});

it('designates a handler for the whole company, with its audit entry, its log line and a note to the other handlers', function (): void {
    Notification::fake();
    Log::spy();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)
        ->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => ''])
        ->assertRedirect(route('tenant.complaints.handlers.index', $this->tenant));

    $handler = ComplaintHandler::query()->where('user_id', $this->hr->id)->sole();
    expect($handler->work_center_id)->toBeNull()
        ->and($handler->designated_by)->toBe($this->owner->id)
        ->and($handler->designated_at->toDateTimeString())->toBe('2026-11-20 18:00:00')
        ->and($handler->ended_at)->toBeNull();
    expect(AuditEntry::query()->where('event', 'complaint_handler.designated')->sole()->subject_id)->toBe($handler->id);
    Log::shouldHaveReceived('info')->with('complaints.handler_designated', ['handler_id' => $handler->id])->once();
    Notification::assertSentOnDemandTimes(HandlersChanged::class, 1);
    Notification::assertSentOnDemand(HandlersChanged::class, fn (HandlersChanged $note, array $channels, $notifiable): bool => $notifiable->routes['mail'] === $this->owner->email);
});

it('goes back to the channel when the person was designated from it (board V4-Quejas-Designar)', function (): void {
    Notification::fake();

    $this->actingAs($this->owner)
        ->from(route('tenant.complaints.channel.show', $this->tenant))
        ->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => ''])
        ->assertRedirect(route('tenant.complaints.channel.show', $this->tenant));
});

it('tells the person designated that they were, for what, and where their cases are, without a word of any case', function (?string $center, string $scope): void {
    Notification::fake();

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => $center === null ? '' : $this->plant->id]);

    Notification::assertSentOnDemand(YouWereDesignated::class, function (YouWereDesignated $note, array $channels, $notifiable) use ($scope): bool {
        $mail = $note->toMail($notifiable);
        $text = implode(' ', [$mail->subject, $mail->greeting, ...$mail->introLines, ...$mail->outroLines]);

        return $notifiable->routes['mail'] === $this->hr->email
            && $mail->subject === 'Te designaron para atender quejas'
            && str_contains($text, "Acme te designó para atender las quejas y denuncias {$scope}.")
            && $mail->actionUrl === route('tenant.complaints.index', $this->tenant);
    });
})->with([
    'the whole company' => [null, 'de toda la empresa'],
    'one center' => ['plant', 'del centro Planta Querétaro'],
]);

it('does not tell whoever designates themselves, as the one who activates the channel', function (): void {
    Notification::fake();

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->owner->id, 'work_center_id' => '']);

    expect(ComplaintHandler::query()->where('user_id', $this->owner->id)->exists())->toBeTrue();
    Notification::assertSentOnDemandTimes(YouWereDesignated::class, 0);
});

it('does not tell the person again when they were already designated for that scope', function (): void {
    Notification::fake();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => '']);

    Notification::assertSentOnDemandTimes(YouWereDesignated::class, 0);
});

it('designates a handler for one work center', function (): void {
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => $this->plant->id])
        ->assertRedirect();

    expect(ComplaintHandler::query()->sole()->work_center_id)->toBe($this->plant->id);
});

it('does not designate someone who cannot handle complaints, who is not in the company or for a center that is not', function (array $data): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    $foreign = memberOf(Tenant::factory()->create(), TenantRole::Hr);
    $foreignCenter = WorkCenter::factory()->create();
    $archived = WorkCenter::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    $map = ['viewer' => $viewer->id, 'foreign' => $foreign->id, 'nobody' => 0, 'foreign-center' => $foreignCenter->id, 'archived' => $archived->id];
    $payload = ['user_id' => $this->hr->id, 'work_center_id' => '', ...array_map(fn ($value) => $map[$value] ?? $value, $data)];

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), $payload)->assertSessionHasErrors(array_key_first($data));

    expect(ComplaintHandler::query()->count())->toBe(0);
})->with([
    'a viewer' => [['user_id' => 'viewer']],
    'a person of another company' => [['user_id' => 'foreign']],
    'a user that does not exist' => [['user_id' => 'nobody']],
    'a center of another company' => [['work_center_id' => 'foreign-center']],
    'an archived center' => [['work_center_id' => 'archived']],
]);

it('does not designate the same person twice for the same scope, but does for another', function (): void {
    Notification::fake();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => ''])->assertSessionHasErrors('user_id');
    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => $this->plant->id])->assertSessionHasNoErrors();

    expect(ComplaintHandler::query()->where('user_id', $this->hr->id)->count())->toBe(2);
});

it('lets a person be designated again once the earlier designation ended', function (): void {
    Notification::fake();
    ComplaintHandler::factory()->company($this->tenant->id)->ended()->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => ''])->assertSessionHasNoErrors();

    expect(ComplaintHandler::query()->whereNull('ended_at')->count())->toBe(1);
});

it('ends a designation without deleting it, with its audit entry, its log line and a note to the others', function (): void {
    Notification::fake();
    Log::spy();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    $handler = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)
        ->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $handler->id]))
        ->assertRedirect(route('tenant.complaints.handlers.index', $this->tenant));

    expect($handler->refresh()->ended_at?->toDateTimeString())->toBe('2026-11-20 18:00:00');
    expect(AuditEntry::query()->where('event', 'complaint_handler.ended')->sole()->subject_id)->toBe($handler->id);
    Log::shouldHaveReceived('info')->with('complaints.handler_ended', ['handler_id' => $handler->id])->once();
    Notification::assertSentOnDemandTimes(HandlersChanged::class, 1);
});

it('does not end a designation that already ended, nor one of another company', function (): void {
    $ended = ComplaintHandler::factory()->company($this->tenant->id)->ended()->create(['user_id' => $this->hr->id]);
    $theirs = ComplaintHandler::factory()->company(Tenant::factory()->create()->id)->create();

    $this->actingAs($this->owner)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $ended->id]))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $theirs->id]))->assertNotFound();
});

it('does not leave an open channel without anybody to handle it', function (): void {
    Notification::fake();
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    $only = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $only->id]))->assertSessionHasErrors('handler');

    expect($only->refresh()->ended_at)->toBeNull();
});

it('lets the last handler go while the channel is not open', function (): void {
    Notification::fake();
    $only = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);

    $this->actingAs($this->owner)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $only->id]))->assertSessionHasNoErrors();

    expect($only->refresh()->ended_at)->not->toBeNull();
});

it('designates whoever activates the channel as its first handler', function (): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.complaints.channel.activate', $this->tenant), ['procedure' => 'Así.', 'acknowledge_within_days' => 3, 'resolve_within_days' => 30, 'retention_months' => 24])
        ->assertRedirect();

    $handler = ComplaintHandler::query()->sole();
    expect($handler->user_id)->toBe($this->hr->id)->and($handler->work_center_id)->toBeNull()->and($handler->designated_by)->toBe($this->hr->id);
});

it('does not designate the activator again when activating an open channel', function (): void {
    ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);
    workIn($this->tenant);
    $this->actingAs($this->hr);

    resolve(ActivateChannel::class)->handle(new App\Modules\Complaints\Support\ChannelSettings('Así.', 3, 30, 24), $this->hr->id);

    expect(ComplaintHandler::query()->count())->toBe(1);
});

it('ends the designations of a person who lost their access to the company', function (): void {
    $mine = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);
    $other = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    $elsewhere = ComplaintHandler::factory()->company(Tenant::factory()->create()->id)->create(['user_id' => $this->hr->id]);

    event(new MemberRemoved($this->tenant->id, $this->hr->id));

    expect($mine->refresh()->ended_at)->not->toBeNull()
        ->and($other->refresh()->ended_at)->toBeNull()
        ->and(ComplaintHandler::query()->withoutGlobalScopes()->find($elsewhere->id)?->ended_at)->toBeNull();
});

it('covers each option of «against whom» with the handlers the conflict leaves', function (): void {
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    workIn($this->tenant);
    $coverage = resolve(HandlerCoverage::class);

    expect($coverage->counts())->toBe(['direct_supervisor' => 1, 'coworker' => 1, 'hr' => 1, 'management' => 0, 'third_party' => 1, 'unknown' => 1]);

    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->hr->id]);

    expect($coverage->counts())->toBe(['direct_supervisor' => 2, 'coworker' => 2, 'hr' => 1, 'management' => 1, 'third_party' => 2, 'unknown' => 2]);
});

it('does not count the handlers whose designation ended, nor those who can no longer handle complaints', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    ComplaintHandler::factory()->company($this->tenant->id)->ended()->create(['user_id' => $this->hr->id]);
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $viewer->id]);
    workIn($this->tenant);

    expect(resolve(HandlerCoverage::class)->counts()['coworker'])->toBe(1);
});

it('routes by the center: a handler of another center does not cover this one', function (): void {
    $other = WorkCenter::factory()->create(['tenant_id' => $this->tenant->id]);
    ComplaintHandler::factory()->forCenter($this->tenant->id, $other->id)->create(['user_id' => $this->owner->id]);
    workIn($this->tenant);
    $coverage = resolve(HandlerCoverage::class);

    expect($coverage->handlerIds(RespondentKind::Coworker, $other->id))->toHaveCount(1)
        ->and($coverage->handlerIds(RespondentKind::Coworker, $this->plant->id))->toBe([])
        ->and($coverage->handlerIds(RespondentKind::Coworker, null))->toBe([]);
});

// Rediseño v2: who receives each kind of complaint moved to the channel's page (board V4-Quejas-Canal); this page lists who
// handles and who could, with the role the designation dialog leaves out by kind.
it('shows the handlers page with who handles and the people who could, and nothing from the cases', function (): void {
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    ComplaintHandler::factory()->forCenter($this->tenant->id, $this->plant->id)->create(['user_id' => $this->hr->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.complaints.handlers.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/handlers')
            ->has('handlers', 2)
            ->where('handlers.0.scope', 'Toda la empresa')
            ->where('handlers.1.scope', 'Planta Querétaro')
            ->has('candidates', 2)
            ->where('candidates', fn ($candidates) => collect($candidates)->contains(['value' => (string) $this->hr->id, 'label' => $this->hr->name, 'role' => 'hr', 'roleLabel' => 'RH']))
            ->missing('coverage')
            ->where('channelStatus', 'inactive'));
});

it('keeps the handlers of one company out of another\'s', function (): void {
    $theirs = ComplaintHandler::factory()->company(Tenant::factory()->create()->id)->create();
    workIn($this->tenant);

    expect(ComplaintHandler::query()->find($theirs->id))->toBeNull();
});

it('lets only the people who manage the channel designate and end handlers', function (TenantRole $role): void {
    $handler = ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->owner->id]);
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->get(route('tenant.complaints.handlers.index', $this->tenant))->assertForbidden();
    $this->actingAs($person)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $this->hr->id, 'work_center_id' => ''])->assertForbidden();
    $this->actingAs($person)->delete(route('tenant.complaints.handlers.destroy', [$this->tenant, $handler->id]))->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);
