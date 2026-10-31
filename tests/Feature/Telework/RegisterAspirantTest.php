<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\AddWorkplace;
use App\Modules\Telework\Actions\RegisterAspirant;
use App\Modules\Telework\Actions\RequestChecklist;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Http\Controllers\TeleworkerController;
use App\Modules\Telework\Jobs\SendPortalInvitation;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Notifications\PortalInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RegisterAspirant::class, AddWorkplace::class, RequestChecklist::class, TeleworkerController::class);

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $this->actingAs($this->hr);
});

/** @return array<string, string> */
function homeOffice(array $overrides = []): array
{
    return ['street' => 'Calle Hidalgo', 'exterior_number' => '45', 'interior_number' => '', 'neighborhood' => 'Centro', 'postal_code' => '76000', 'municipality' => 'Querétaro', 'state' => 'QUE', ...$overrides];
}

/** @return array<string, mixed> */
function aspirantForm(array $overrides = []): array
{
    return ['employee_id' => test()->employee->id, 'telework_percentage' => 50, 'workplaces' => [homeOffice()], ...$overrides];
}

function registerUrl(?WorkCenter $center = null): string
{
    return route('tenant.telework.teleworkers.store', [test()->tenant, ($center ?? test()->center)->id]);
}

function aspirant(): Teleworker
{
    workIn(test()->tenant);

    return Teleworker::query()->sole();
}

it('registers an aspirant with a percentage over 40', function (int $percentage): void {
    $this->post(registerUrl(), aspirantForm(['telework_percentage' => $percentage]))->assertSessionHasNoErrors();

    $teleworker = aspirant();

    expect($teleworker->status)->toBe(TeleworkerStatus::Aspirant)
        ->and($teleworker->telework_percentage)->toBe($percentage)
        ->and($teleworker->employee_id)->toBe($this->employee->id)
        ->and($teleworker->work_center_id)->toBe($this->center->id)
        ->and($teleworker->workplaces)->toHaveCount(1)
        ->and($teleworker->workplaces[0]->street)->toBe('Calle Hidalgo')
        ->and($teleworker->workplaces[0]->interior_number)->toBeNull();
})->with([41, 100]);

it('redirects to the teleworker', function (): void {
    $response = $this->post(registerUrl(), aspirantForm());

    $response->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, aspirant()->id]));
});

it('refuses a percentage of 40 or less, or over 100', function (mixed $percentage): void {
    $this->post(registerUrl(), aspirantForm(['telework_percentage' => $percentage]))->assertSessionHasErrors('telework_percentage');

    expect(Teleworker::query()->count())->toBe(0);
})->with([40, 101, 0, 'abc', '']);

it('requires at least one workplace', function (mixed $workplaces): void {
    $this->post(registerUrl(), aspirantForm(['workplaces' => $workplaces]))->assertSessionHasErrors('workplaces');

    expect(Teleworker::query()->count())->toBe(0);
})->with([[[]], [null]]);

it('validates the address of each workplace like a work center', function (string $field, string $value): void {
    $this->post(registerUrl(), aspirantForm(['workplaces' => [homeOffice(), homeOffice([$field => $value])]]))->assertSessionHasErrors("workplaces.1.{$field}");

    expect(Teleworker::query()->count())->toBe(0);
})->with([['street', ''], ['exterior_number', ''], ['neighborhood', ''], ['postal_code', '1234'], ['postal_code', '7600A'], ['municipality', ''], ['state', 'XYZ']]);

it('rejects an employee of another work center, company or deactivated', function (Closure $employee): void {
    $this->post(registerUrl(), aspirantForm(['employee_id' => $employee($this)->id]))->assertSessionHasErrors('employee_id');

    expect(Teleworker::query()->count())->toBe(0);
})->with([
    'another work center' => fn ($test) => EmployeeFactory::new()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => WorkCenter::factory()->for($test->tenant)->create()->id]),
    'another company' => fn ($test) => EmployeeFactory::new()->create(),
    'deactivated' => fn ($test) => EmployeeFactory::new()->deactivated()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->center->id]),
]);

it('rejects an employee that does not exist', function (): void {
    $this->post(registerUrl(), aspirantForm(['employee_id' => '00000000-0000-4000-8000-000000000000']))->assertSessionHasErrors('employee_id');
    $this->post(registerUrl(), aspirantForm(['employee_id' => 'nope']))->assertSessionHasErrors('employee_id');
});

it('refuses while the work center has no published checklist', function (): void {
    workIn($this->tenant);
    Checklist::query()->update(['status' => ChecklistStatus::Draft, 'published_at' => null]);

    $this->post(registerUrl(), aspirantForm())->assertSessionHasErrors(['work_center' => 'La Comisión aún no publica la lista']);

    expect(Teleworker::query()->count())->toBe(0)->and(PortalLink::query()->count())->toBe(0);
});

it('creates one Sección I application per workplace with the latest published version', function (): void {
    $newest = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'version' => 2]);

    $this->post(registerUrl(), aspirantForm(['workplaces' => [homeOffice(), homeOffice(['street' => 'Calle Juárez'])]]))->assertSessionHasNoErrors();

    $applications = ChecklistApplication::query()->get();

    expect($applications)->toHaveCount(2)
        ->and($applications->pluck('section')->unique()->all())->toBe([Section::One])
        ->and($applications->pluck('checklist_id')->unique()->all())->toBe([$newest->id])
        ->and($applications->pluck('workplace_id')->sort()->values()->all())->toBe(aspirant()->workplaces->pluck('id')->sort()->values()->all())
        ->and($applications->every(fn (ChecklistApplication $application): bool => $application->submitted_at === null))->toBeTrue();
});

it('rejects a second open period for the same employee', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $this->post(registerUrl(), aspirantForm())->assertSessionHasErrors('employee_id');

    expect(Teleworker::query()->count())->toBe(1)->and(Workplace::query()->count())->toBe(1);
});

it('emails the portal link and stores only its hash', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();

    $link = PortalLink::query()->sole();
    $sent = [];
    Notification::assertSentOnDemand(PortalInvitation::class, function (PortalInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $sent = ['url' => $notification->toMail($notifiable)->actionUrl, 'to' => $notifiable->routes['mail'], 'channels' => $channels];

        return true;
    });
    $token = basename($sent['url']);

    expect($sent['to'])->toBe('ana@acme.test')
        ->and($sent['channels'])->toBe(['mail'])
        ->and($sent['url'])->toBe(route('portal.show', $token))
        ->and($link->token_hash)->toBe(hash('sha256', $token))
        ->and($link->employee_id)->toBe($this->employee->id);
    $this->assertDatabaseMissing('portal_links', ['token_hash' => $token]);

    auth()->logout();
    $this->get(route('portal.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', false)->has('pending.checklists', 1));
});

it('sends no email to someone without one: RH hands them a link from the page', function (): void {
    $this->employee->forceFill(['email' => null])->save();

    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();

    Notification::assertNothingSent();
    expect(PortalLink::query()->count())->toBe(0);
});

it('queues only ids: the plain token never reaches the queue, and the job makes a working link', function (): void {
    Queue::fake();

    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();

    $job = null;
    Queue::assertPushed(SendPortalInvitation::class, function (SendPortalInvitation $pushed) use (&$job): bool {
        $job = $pushed;

        return $pushed->tenantId === $this->tenant->id && $pushed->employeeId === $this->employee->id;
    });
    expect(PortalLink::query()->count())->toBe(0);

    app()->call($job->handle(...));

    $url = null;
    Notification::assertSentOnDemand(PortalInvitation::class, function (PortalInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url): bool {
        $url = $notification->toMail($notifiable)->actionUrl;

        return true;
    });
    $token = basename($url);

    expect(serialize($job))->not->toContain($token)
        ->and($job)->toBeInstanceOf(ShouldQueue::class)
        ->and(new PortalInvitation($token, 'Ana', 'Acme'))->not->toBeInstanceOf(ShouldQueue::class)
        ->and(PortalLink::query()->sole()->token_hash)->toBe(hash('sha256', $token));
    auth()->logout();
    $this->get(route('portal.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', false));
});

it('sends nothing when the person left before the job runs', function (): void {
    $this->employee->forceFill(['deactivated_at' => now()])->save();

    app()->call((new SendPortalInvitation($this->tenant->id, $this->employee->id))->handle(...));

    Notification::assertNothingSent();
    expect(PortalLink::query()->count())->toBe(0);
});

it('sends nothing when the company may not be used by the time the job runs', function (): void {
    Tenant::query()->whereKey($this->tenant->id)->update(['deactivated_at' => now()]);

    app()->call((new SendPortalInvitation($this->tenant->id, $this->employee->id))->handle(...));

    Notification::assertNothingSent();
    workIn(tenantOf($this->tenant->id));
    expect(PortalLink::query()->count())->toBe(0);
});

it('answers with the pending application when the insert races, even inside a transaction', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $workplace = aspirant()->workplaces[0];
    $rival = (string) Str::uuid();
    // Another request commits its own Sección II after ours looked and before it inserts: the unique index refuses ours.
    Checklist::retrieved(function () use ($workplace, $rival): void {
        ChecklistApplication::unguarded(fn () => DB::table('checklist_applications')->insert([
            'id' => $rival, 'tenant_id' => $workplace->tenant_id, 'workplace_id' => $workplace->id, 'checklist_id' => $this->checklist->id,
            'section' => 2, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]));
    });

    $found = DB::transaction(function () use ($workplace): ChecklistApplication {
        $found = resolve(RequestChecklist::class)->handle($workplace, Section::Two);
        // The outer transaction is still usable: the failed insert was rolled back to its own savepoint.
        ChecklistApplication::query()->count();

        return $found;
    });

    expect($found->id)->toBe($rival);
});

it('logs telework.aspirant_registered with ids only and audits it', function (): void {
    Log::spy();

    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();

    $teleworker = aspirant();
    $expected = ['teleworker_id' => $teleworker->id, 'employee_id' => $this->employee->id, 'work_center_id' => $this->center->id, 'workplaces' => 1];
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.aspirant_registered' && $context === $expected)->once();
    $entry = AuditEntry::query()->where('event', 'telework.aspirant_registered')->sole();

    expect($entry->subject_id)->toBe($teleworker->id)->and($entry->metadata)->toEqual([...$expected, 'registered_by' => $this->hr->id]);
    foreach (['Ana', 'ana@acme.test', 'Hidalgo'] as $private) {
        expect(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain($private);
    }
});

it('does not run the invitation job until the surrounding transaction commits', function (): void {
    // The sync queue runs a job as it is pushed, so a mail inside the transaction would mean it was pushed early.
    DB::transaction(function (): void {
        SendPortalInvitation::dispatch($this->tenant->id, $this->employee->id);

        Notification::assertNothingSent();
    });

    Notification::assertSentOnDemandTimes(PortalInvitation::class, 1);
    expect(new SendPortalInvitation($this->tenant->id, $this->employee->id))->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('shows the registration page of a work center with its checklist state', function (): void {
    $this->get(route('tenant.telework.teleworkers.create', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/teleworker')
            ->where('teleworker', null)
            ->where('workCenter.id', $this->center->id)
            ->where('hasChecklist', true)
            ->where('states.QUE', 'Querétaro')
            ->missing('employees'));
});

it('tells the registration page when the Comisión has not published the list', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();

    $this->get(route('tenant.telework.teleworkers.create', [$this->tenant, $other->id]))
        ->assertInertia(fn (Assert $page) => $page->where('hasChecklist', false));
});

it('shows a teleworker with their workplaces and the missing email', function (): void {
    $this->employee->forceFill(['email' => null])->save();
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, aspirant()->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('telework/teleworker')
            ->where('teleworker.name', 'Ana Ruiz')
            ->where('teleworker.status', 'aspirant')
            ->where('teleworker.percentage', 50)
            ->where('teleworker.hasEmail', false)
            ->where('workCenter.id', $this->center->id)
            ->has('teleworker.workplaces', 1, fn (Assert $place) => $place->has('id')->where('address', 'Calle Hidalgo 45, Centro, Querétaro, Querétaro')->where('label', 'Pendiente de contestar'))
            ->missing('teleworker.link'));
});

it('issues a new link to copy and invalidates the previous', function (): void {
    $this->employee->forceFill(['email' => null])->save();
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $teleworker = aspirant();
    expect(PortalLink::query()->count())->toBe(0);

    $response = $this->post(route('tenant.telework.teleworkers.link', [$this->tenant, $teleworker->id]));

    $response->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $teleworker->id]));
    $flash = session('inertia.flash_data')['portalLink'] ?? null;
    expect($flash)->toBeString()->toStartWith(url('/portal/'));
    $token = basename($flash);
    expect(PortalLink::query()->sole()->token_hash)->toBe(hash('sha256', $token));

    auth()->logout();
    $this->get(route('portal.show', $token))->assertInertia(fn (Assert $page) => $page->where('unavailable', false));
});

it('does not hand a link to anyone but RH and the owner', function (TenantRole $role): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $teleworker = aspirant();
    $this->actingAs(memberOf($this->tenant, $role));

    $this->post(route('tenant.telework.teleworkers.link', [$this->tenant, $teleworker->id]))->assertForbidden();
    $this->post(registerUrl(), aspirantForm())->assertForbidden();
    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $teleworker->id]))->assertForbidden();
    $this->get(route('tenant.telework.teleworkers.create', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->get(route('tenant.telework.teleworkers.employees', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->post(route('tenant.telework.teleworkers.workplaces.store', [$this->tenant, $teleworker->id]), homeOffice())->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('answers 404, not 403, for a teleworker or work center of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = TeleworkerFactory::new()->create(['tenant_id' => $other->id]);
    $foreignCenter = WorkCenter::factory()->for($other)->create();

    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->post(route('tenant.telework.teleworkers.link', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->post(route('tenant.telework.teleworkers.workplaces.store', [$this->tenant, $foreign->id]), homeOffice())->assertNotFound();
    $this->get(route('tenant.telework.teleworkers.show', [$this->tenant, 'not-a-uuid']))->assertNotFound();
    $this->post(registerUrl($foreignCenter), aspirantForm())->assertNotFound();
    $this->get(route('tenant.telework.teleworkers.create', [$this->tenant, $foreignCenter->id]))->assertNotFound();
    $this->get(route('tenant.telework.teleworkers.employees', [$this->tenant, $foreignCenter->id]))->assertNotFound();
});

it('searches the active people of the center by name for the picker', function (): void {
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana López']);
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Beto Ana']);
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Baja']);
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id, 'name' => 'Ana Lejos']);

    $found = $this->getJson(route('tenant.telework.teleworkers.employees', [$this->tenant, $this->center->id, 'q' => 'ana']))->assertOk()->json();

    expect(array_column($found, 'label'))->toHaveCount(3)->toContain('Ana Ruiz')->not->toContain('Ana Baja')->not->toContain('Ana Lejos')
        ->and(array_keys($found[0]))->toBe(['value', 'label']);
});

it('adds another workplace with its Sección I application', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $teleworker = aspirant();

    $this->post(route('tenant.telework.teleworkers.workplaces.store', [$this->tenant, $teleworker->id]), homeOffice(['street' => 'Calle Juárez']))
        ->assertRedirect(route('tenant.telework.teleworkers.show', [$this->tenant, $teleworker->id]));
    $this->post(route('tenant.telework.teleworkers.workplaces.store', [$this->tenant, $teleworker->id]), homeOffice(['postal_code' => '1']))->assertSessionHasErrors('postal_code');

    expect(Workplace::query()->count())->toBe(2)->and(ChecklistApplication::query()->count())->toBe(2);
});

it('does not add a workplace to a period that already ended', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $teleworker = aspirant();
    $teleworker->forceFill(['status' => TeleworkerStatus::Ended, 'started_at' => now()->subYear(), 'ended_at' => now(), 'end_reason' => 'other'])->save();

    $this->post(route('tenant.telework.teleworkers.workplaces.store', [$this->tenant, $teleworker->id]), homeOffice())->assertNotFound();
});

it('asks for a checklist once while it is pending, and again after it was sent', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $workplace = aspirant()->workplaces[0];
    $request = resolve(RequestChecklist::class);

    $pending = ChecklistApplication::query()->sole();
    expect($request->handle($workplace, Section::One)->id)->toBe($pending->id)
        ->and(ChecklistApplication::query()->count())->toBe(1);

    $second = $request->handle($workplace, Section::Two);
    expect($second->section)->toBe(Section::Two)->and($second->checklist_id)->toBe($this->checklist->id)->and($second->requested_at)->not->toBeNull();

    $pending->forceFill(['submitted_at' => now()])->save();
    expect($request->handle($workplace, Section::One)->id)->not->toBe($pending->id)->and(ChecklistApplication::query()->count())->toBe(3);
});

it('cannot ask for a checklist the Comisión has not published', function (): void {
    $this->post(registerUrl(), aspirantForm())->assertSessionHasNoErrors();
    $workplace = aspirant()->workplaces[0];
    Checklist::query()->update(['status' => ChecklistStatus::Draft, 'published_at' => null]);

    expect(fn () => resolve(RequestChecklist::class)->handle($workplace, Section::Two))->toThrow(ValidationException::class);
});
