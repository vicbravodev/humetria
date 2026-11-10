<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\CancelInvitation;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Listeners\CancelOnDeactivation;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(CancelInvitation::class, CancelOnDeactivation::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->survey = Survey::factory()->active('2026-11-20')->create(['tenant_id' => $this->tenant->id]);
    $this->employee = staffOf($this->tenant, $this->plant, 1)->first();
    $this->invitation = Invitation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'survey_id' => $this->survey->id,
        'employee_id' => $this->employee->id,
        'token_hash' => Invitation::hashToken('the-token'),
        'work_center_id' => $this->plant->id,
        'area_id' => $this->employee->area_id,
        'tenure_band' => '1to3',
    ]);
});

it('cancels the link of someone who is deactivated before answering', function (): void {
    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    expect($this->invitation->fresh()?->cancelled)->toBeTrue();
});

it('opens «not available» at the link of someone who left', function (): void {
    $this->get(route('engagement.survey.show', 'the-token'))->assertInertia(fn ($page) => $page->where('unavailable', null));

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    auth()->logout();
    $this->get(route('engagement.survey.show', 'the-token'))->assertInertia(fn ($page) => $page->where('unavailable', 'invalid')->where('sections', []));
});

it('keeps what someone already answered: it cannot be taken back', function (): void {
    $this->invitation->update(['answered' => true]);

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]))->assertRedirect();

    $kept = $this->invitation->fresh();
    expect($kept?->answered)->toBeTrue()->and($kept?->cancelled)->toBeFalse();
});

it('leaves closed surveys and other people as they were', function (): void {
    $closed = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'kind' => App\Modules\Engagement\Enums\SurveyKind::Pulse]);
    $past = Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $closed->id, 'employee_id' => $this->employee->id]);
    $colleague = Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id]);

    $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $this->employee->id]));

    expect($past->fresh()?->cancelled)->toBeFalse()->and($colleague->fresh()?->cancelled)->toBeFalse();
});

it('deactivates a person and cancels their link together, or neither', function (): void {
    resolve(Companies::class)->enter($this->tenant->id);
    Event::listen(EmployeeDeactivated::class, fn () => throw new RuntimeException('Something after the cancelling failed.'));

    expect(fn () => resolve(DeactivateEmployee::class)->handle($this->employee))->toThrow(RuntimeException::class);

    expect($this->employee->fresh()?->deactivated_at)->toBeNull()
        ->and($this->invitation->fresh()?->cancelled)->toBeFalse();
});

it('does not change the snapshot when someone moves to another center, area or manager', function (): void {
    $warehouse = WorkCenter::factory()->for($this->tenant)->create();
    $boss = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->employee->area_id]);
    $before = $this->invitation->fresh()?->only(['work_center_id', 'area_id', 'tenure_band', 'manager_group_id', 'comment_group_id', 'about_manager_id', 'cancelled']);

    $this->actingAs($this->owner)->put(route('tenant.employees.update', [$this->tenant, $this->employee->id]), [
        'name' => $this->employee->name,
        'email' => $this->employee->email,
        'mobile' => $this->employee->mobile,
        'gender' => $this->employee->gender->value,
        'work_center_id' => $warehouse->id,
        'area' => 'Almacén',
        'job_title' => $this->employee->job_title,
        'manager_id' => $boss->id,
    ])->assertSessionHasNoErrors();

    expect($this->employee->fresh()?->work_center_id)->toBe($warehouse->id)
        ->and($this->invitation->fresh()?->only(['work_center_id', 'area_id', 'tenure_band', 'manager_group_id', 'comment_group_id', 'about_manager_id', 'cancelled']))->toBe($before);
});

it('sends no email to someone who was cancelled while waiting in the queue', function (): void {
    Notification::fake();
    $batch = 'b6a3f4c0-6d35-4f0e-8a58-1d2c3b4a5e6f';
    $this->invitation->update(['invitation_status' => InvitationStatus::Queued, 'invitation_batch' => $batch]);
    $job = new SendInvitations($this->survey->id, $batch, [$this->invitation->id]);
    workIn($this->tenant);
    resolve(CancelInvitation::class)->handle($this->employee->id);

    $job->handle();

    Notification::assertNothingSent();
});

it('touches only that company\'s invitations, and logs the count without the person\'s answers', function (): void {
    $other = Invitation::factory()->create();
    workIn($this->tenant);
    Log::spy();

    $outside = resolve(CancelInvitation::class)->handle($other->employee_id);
    $inside = resolve(CancelInvitation::class)->handle($this->employee->id);
    $again = resolve(CancelInvitation::class)->handle($this->employee->id);

    expect($outside)->toBe(0)->and($inside)->toBe(1)->and($again)->toBe(0)
        ->and(Invitation::query()->withoutGlobalScopes()->whereKey($other->id)->sole()->cancelled)->toBeFalse();
    Log::shouldHaveReceived('info')->with('engagement.invitation_cancelled', ['employee_id' => $this->employee->id, 'invitations' => 1])->once();
});
