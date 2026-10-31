<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\RegisterTraining;
use App\Modules\Telework\Enums\TrainingModality;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Http\Controllers\TrainingController;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Support\TrainingDue;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TrainingFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

const QUERIES_TRAININGS = 17;

mutates(RegisterTraining::class, TrainingDue::class, TrainingController::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->ana = trainee('Ana Ruiz');
    $this->beto = trainee('Beto Soto');
    $this->actingAs($this->hr);
    workIn($this->tenant);
});

function trainee(string $name, ?WorkCenter $center = null, array $state = []): App\Modules\Organization\Models\Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => ($center ?? test()->center)->id, 'name' => $name, ...$state]);
}

/** @return array<string, mixed> */
function trainingForm(array $overrides = []): array
{
    return [
        'work_center_id' => test()->center->id,
        'held_on' => '2026-02-20',
        'modality' => 'remote',
        'topics' => ['hazards', 'ict'],
        'employee_ids' => [test()->ana->id, test()->beto->id],
        ...$overrides,
    ];
}

function trainingUrl(): string
{
    return route('tenant.telework.trainings.store', test()->tenant);
}

function trainingPdf(string $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n"): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, $content);

    return new UploadedFile($path, 'lista.pdf', null, null, true);
}

/** A training of the test's center held on `$on`, attended by `$people`. */
function heldOn(string $on, array $people, array $topics = ['hazards'], ?WorkCenter $center = null): Training
{
    $training = TrainingFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => ($center ?? test()->center)->id, 'held_on' => $on, 'topics' => $topics]);

    foreach ($people as $person) {
        TrainingAttendee::query()->create(['tenant_id' => test()->tenant->id, 'training_id' => $training->id, 'employee_id' => $person->id]);
    }

    return $training;
}

/** An active teleworker (roster) of the test's center who joined on `$on`. */
function joinedOn(string $on, ?App\Modules\Organization\Models\Employee $person = null): App\Modules\Telework\Models\Teleworker
{
    return TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => ($person ?? test()->ana)->id, 'started_at' => $on]);
}

it('registers a training with its 8.1 topics and attendees from the roster of the center', function (): void {
    Log::spy();

    $this->post(trainingUrl(), trainingForm())->assertSessionHasNoErrors()->assertRedirect(route('tenant.telework.trainings.index', $this->tenant));

    $training = Training::query()->sole();
    expect($training->work_center_id)->toBe($this->center->id)
        ->and($training->held_on->toDateString())->toBe('2026-02-20')
        ->and($training->modality)->toBe(TrainingModality::Remote)
        ->and($training->topics)->toBe([TrainingTopic::Hazards, TrainingTopic::Ict])
        ->and($training->evidence_path)->toBeNull()
        ->and($training->registered_by)->toBe($this->hr->id)
        ->and(TrainingAttendee::query()->where('training_id', $training->id)->pluck('employee_id')->sort()->values()->all())->toBe(collect([$this->ana->id, $this->beto->id])->sort()->values()->all())
        ->and(AuditEntry::query()->where('event', 'telework.training_registered')->sole()->subject_id)->toBe($training->id);
    Log::shouldHaveReceived('info')->with('telework.training_registered', ['training_id' => $training->id, 'work_center_id' => $this->center->id, 'topic_count' => 2, 'attendee_count' => 2])->once();
});

it('keeps the optional evidence PDF on the private disk and serves it to those who may', function (): void {
    $this->post(trainingUrl(), trainingForm(['evidence' => trainingPdf()]))->assertSessionHasNoErrors();

    $training = Training::query()->sole();
    Storage::disk(config()->string('filesystems.telework_files'))->assertExists((string) $training->evidence_path);
    $this->get(route('tenant.telework.trainings.evidence', [$this->tenant, $training->id]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.telework.trainings.evidence', [$this->tenant, $training->id]))->assertForbidden();
    $this->actingAs($this->hr)->get(route('tenant.telework.trainings.evidence', [$this->tenant, 'nope']))->assertNotFound();
});

it('answers 404 for the evidence of a training without file or of another company', function (): void {
    $training = heldOn('2026-02-01', [$this->ana]);
    $this->get(route('tenant.telework.trainings.evidence', [$this->tenant, $training->id]))->assertNotFound();

    $foreign = TrainingFactory::new()->create(['evidence_path' => 'x.pdf']);
    $this->get(route('tenant.telework.trainings.evidence', [$this->tenant, $foreign->id]))->assertNotFound();
});

it('refuses a bad training and writes nothing', function (array $overrides, string $field): void {
    $this->post(trainingUrl(), trainingForm($overrides))->assertSessionHasErrors($field);

    expect(Training::query()->count())->toBe(0)
        ->and(TrainingAttendee::query()->count())->toBe(0)
        ->and(Storage::disk(config()->string('filesystems.telework_files'))->allFiles())->toBe([]);
})->with([
    'no topics' => [['topics' => []], 'topics'],
    'unknown topic' => [['topics' => ['hazards', 'cooking']], 'topics.1'],
    'no attendees' => [['employee_ids' => []], 'employee_ids'],
    'unknown modality' => [['modality' => 'telepathy'], 'modality'],
    'future date' => [['held_on' => '2026-03-02'], 'held_on'],
    'no date' => [['held_on' => ''], 'held_on'],
    'no center' => [['work_center_id' => ''], 'work_center_id'],
    'not a pdf' => [['evidence' => 'text'], 'evidence'],
]);

it('refuses attendees who are not active people of that center', function (): void {
    $elsewhere = trainee('Carla Paz', $this->other);
    $gone = trainee('Dora Gil', null, ['deactivated_at' => now()]);
    $foreign = EmployeeFactory::new()->create();

    foreach ([$elsewhere, $gone, $foreign] as $person) {
        $this->post(trainingUrl(), trainingForm(['employee_ids' => [$this->ana->id, $person->id]]))->assertSessionHasErrors('employee_ids');
    }

    $this->post(trainingUrl(), trainingForm(['employee_ids' => [$this->ana->id, 'not-a-uuid']]))->assertSessionHasErrors('employee_ids');
    expect(Training::query()->count())->toBe(0);
});

it('refuses a center of another company or one that is archived', function (): void {
    $foreign = WorkCenter::factory()->create();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    $this->post(trainingUrl(), trainingForm(['work_center_id' => $foreign->id]))->assertSessionHasErrors('work_center_id');
    $this->post(trainingUrl(), trainingForm(['work_center_id' => $archived->id]))->assertSessionHasErrors('work_center_id');
    $this->post(trainingUrl(), trainingForm(['work_center_id' => 'x']))->assertSessionHasErrors('work_center_id');
});

it('counts an attendee once and accepts a training held today', function (): void {
    $this->post(trainingUrl(), trainingForm(['held_on' => '2026-03-01', 'employee_ids' => [$this->ana->id, $this->ana->id]]))->assertSessionHasNoErrors();

    expect(TrainingAttendee::query()->count())->toBe(1);
});

it('lets only owner and hr register trainings', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->post(trainingUrl(), trainingForm())->assertStatus($status);
})->with([[TenantRole::Owner, 302], [TenantRole::Hr, 302], [TenantRole::Viewer, 403], [TenantRole::Committee, 403]]);

it('refuses in the database a training without topics and a repeated attendee', function (): void {
    // Each attempt in its own savepoint: a failed statement would otherwise poison the test's transaction.
    $blocked = fn (Closure $attempt) => expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class);
    $training = heldOn('2026-02-01', [$this->ana]);

    $blocked(fn () => TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'topics' => []]));
    $blocked(fn () => TrainingAttendee::query()->create(['tenant_id' => $this->tenant->id, 'training_id' => $training->id, 'employee_id' => $this->ana->id]));
    expect(Training::query()->count())->toBe(1);
});

it('keeps one company\'s trainings out of another\'s queries', function (): void {
    heldOn('2026-02-01', [$this->ana]);
    workIn(Tenant::factory()->create());

    expect(Training::query()->count())->toBe(0)->and(TrainingAttendee::query()->count())->toBe(0);
});

it('rejects a future date in the action, in the company time zone', function (): void {
    // 03:00 UTC on 1 March is still 28 February in Mexico City.
    $this->travelTo('2026-03-01 03:00:00');
    $heldOn = fn (string $day) => fn () => resolve(RegisterTraining::class)->handle($this->center->id, CarbonImmutable::parse($day), TrainingModality::Mixed, [TrainingTopic::Ict], [$this->ana->id], null, $this->hr->id);

    expect($heldOn('2026-03-01'))->toThrow(ValidationException::class);
    expect($heldOn('2026-02-28')())->toBeInstanceOf(Training::class);
});

it('refuses in the database an unknown topic and reads past one it does not know', function (): void {
    expect(fn () => DB::transaction(fn () => TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'topics' => ['hazards', 'cooking']])))->toThrow(QueryException::class);

    $training = heldOn('2026-02-01', [$this->ana], ['hazards']);
    DB::table('trainings')->where('id', $training->id)->update(['topics' => json_encode(['hazards'])]);
    expect($training->refresh()->topics)->toBe([TrainingTopic::Hazards]);
});

it('rejects the action with no attendees even when called outside the request', function (): void {
    expect(fn () => resolve(RegisterTraining::class)->handle($this->center->id, CarbonImmutable::parse('2026-02-01'), TrainingModality::Mixed, [TrainingTopic::Ict], [], null, $this->hr->id))->toThrow(ValidationException::class);
});

describe('the 8.1 topics not covered', function (): void {
    it('lists the topics no training covered in the last 12 months', function (): void {
        heldOn('2025-01-10', [$this->ana], ['hazards', 'ict']);
        heldOn('2026-01-10', [$this->ana], ['psychosocial', 'policy_understanding']);

        $uncovered = resolve(TrainingDue::class)->uncoveredTopics($this->center->id, CarbonImmutable::parse('2026-03-01'));

        expect(array_map(fn (TrainingTopic $topic) => $topic->value, $uncovered))->toBe(['hazards', 'ergonomic_postures', 'ergonomic_repetitive', 'ergonomic_sitting', 'policy_application', 'ict']);
    });

    it('counts a training held exactly 12 months ago and not one the day before', function (): void {
        heldOn('2025-03-01', [$this->ana], ['hazards']);
        heldOn('2025-02-28', [$this->ana], ['ict']);

        $uncovered = array_map(fn (TrainingTopic $topic) => $topic->value, resolve(TrainingDue::class)->uncoveredTopics($this->center->id, CarbonImmutable::parse('2026-03-01')));

        expect($uncovered)->not->toContain('hazards')->and($uncovered)->toContain('ict');
    });

    it('ignores the trainings of other centers and lists all eight when there are none', function (): void {
        heldOn('2026-02-01', [trainee('Carla Paz', $this->other)], ['hazards'], $this->other);

        expect(resolve(TrainingDue::class)->uncoveredTopics($this->center->id, CarbonImmutable::parse('2026-03-01')))->toHaveCount(8);
    });
});

describe('the due date', function (): void {
    it('computes it from the join date when the person never trained', function (): void {
        $teleworker = joinedOn('2026-01-10 15:00:00');
        $due = resolve(TrainingDue::class);

        expect($due->dueOn($teleworker)->toDateString())->toBe('2027-01-10')
            ->and($due->warnOn($teleworker)->toDateString())->toBe('2026-12-10');
    });

    it('computes it from the last training the person attended in that center', function (): void {
        $teleworker = joinedOn('2026-01-10 15:00:00');
        heldOn('2026-02-01', [$this->ana, $this->beto]);
        heldOn('2026-02-20', [$this->ana]);
        heldOn('2026-02-25', [$this->beto]);
        heldOn('2026-02-26', [trainee('Carla Paz', $this->other)], ['hazards'], $this->other);
        $due = resolve(TrainingDue::class);

        expect($due->dueOn($teleworker)->toDateString())->toBe('2027-02-20')
            ->and($due->warnOn($teleworker)->toDateString())->toBe('2027-01-20');
    });

    it('reads the join day in the company time zone, not UTC', function (): void {
        // 03:00 UTC on the 11th is still the 10th in Mexico City.
        $teleworker = joinedOn('2026-01-11 03:00:00');

        expect(resolve(TrainingDue::class)->dueOn($teleworker)->toDateString())->toBe('2027-01-10');
    });

    it('does not overflow at the end of a month', function (): void {
        $teleworker = joinedOn('2024-02-29 18:00:00');

        $due = resolve(TrainingDue::class);
        expect($due->dueOn($teleworker)->toDateString())->toBe('2025-02-28')
            ->and($due->warnOn($teleworker)->toDateString())->toBe('2025-01-28');
    });

    it('does not count a training held before the person joined', function (): void {
        $teleworker = joinedOn('2026-01-10 15:00:00');
        heldOn('2026-01-09', [$this->ana]);
        $due = resolve(TrainingDue::class);

        expect($due->dueOn($teleworker)->toDateString())->toBe('2027-01-10');
    });

    it('counts a training held on the day the person joined, read in the company time zone', function (): void {
        // 03:00 UTC on the 11th is the 10th in Mexico City, so a training of the 10th is on the start day.
        $teleworker = joinedOn('2026-01-11 03:00:00');
        heldOn('2026-01-10', [$this->ana]);

        expect(resolve(TrainingDue::class)->dueOn($teleworker)->toDateString())->toBe('2027-01-10');

        heldOn('2026-01-20', [$this->ana]);
        expect(resolve(TrainingDue::class)->dueOn($teleworker)->toDateString())->toBe('2027-01-20');
    });

    it('ignores the trainings of a previous period of a person who returned', function (): void {
        TeleworkerFactory::new()->ended()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'started_at' => '2025-01-10 15:00:00']);
        heldOn('2025-12-01', [$this->ana]);
        $again = joinedOn('2026-03-01 15:00:00');

        $due = resolve(TrainingDue::class);
        expect($due->dueOn($again)->toDateString())->toBe('2027-03-01');

        heldOn('2026-03-05', [$this->ana]);
        expect($due->dueOn($again)->toDateString())->toBe('2027-03-05');
    });

    it('ignores a training the person did not attend', function (): void {
        $teleworker = joinedOn('2026-01-10 15:00:00');
        heldOn('2026-02-20', [$this->beto]);

        expect(resolve(TrainingDue::class)->dueOn($teleworker)->toDateString())->toBe('2027-01-10');
    });
});

describe('the page', function (): void {
    it('lists the trainings and the roster with their due dates, newest first and soonest due first', function (): void {
        $first = heldOn('2026-01-15', [$this->ana, $this->beto], ['hazards']);
        $latest = heldOn('2026-02-15', [$this->beto], ['ict', 'psychosocial']);
        joinedOn('2025-01-05 15:00:00', trainee('Carla Paz'));
        joinedOn('2025-01-05 15:00:00', $this->ana);
        joinedOn('2026-01-10 15:00:00', $this->beto);

        $this->get(route('tenant.telework.trainings.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
            ->component('telework/trainings')
            ->has('trainings.data', 2)
            ->where('trainings.data.0.id', $latest->id)
            ->where('trainings.data.0.attendees', 1)
            ->where('trainings.data.0.topics', ['Manejo de las tecnologías de la información y comunicación (TIC)', 'Factores de riesgo psicosocial en el teletrabajo'])
            ->where('trainings.data.0.modality', 'Remota')
            ->where('trainings.data.1.id', $first->id)
            ->has('people.data', 3)
            ->where('people.data.0.name', 'Carla Paz')
            ->where('people.data.0.dueOn', '2026-01-05')
            ->where('people.data.0.status', 'overdue')
            ->where('people.data.0.lastTrainingOn', null)
            ->where('people.data.1.name', 'Ana Ruiz')
            ->where('people.data.1.dueOn', '2027-01-15')
            ->where('people.data.1.status', 'ok')
            ->where('people.data.1.lastTrainingOn', '2026-01-15')
            ->where('people.data.2.dueOn', '2027-02-15')
            ->where('uncovered', null)
            ->has('topics', 8)
            ->has('modalities', 3));
    });

    it('marks who is within a month of the due date', function (): void {
        joinedOn('2025-03-20 15:00:00');

        $this->get(route('tenant.telework.trainings.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
            ->where('people.data.0.dueOn', '2026-03-20')
            ->where('people.data.0.status', 'due_soon'));
    });

    it('filters by work center and then shows the topics that center has not covered', function (): void {
        heldOn('2026-02-01', [$this->ana], ['hazards']);
        heldOn('2026-02-01', [trainee('Carla Paz', $this->other)], ['ict'], $this->other);

        $this->get(route('tenant.telework.trainings.index', [$this->tenant, 'center' => $this->center->id]))->assertInertia(fn (Assert $page) => $page
            ->has('trainings.data', 1)
            ->where('filters.center', ['value' => $this->center->id, 'label' => 'Planta Norte'])
            ->has('uncovered', 7)
            ->where('uncovered.0.value', 'ergonomic_postures'));
    });

    it('ignores a center that is not the company\'s', function (): void {
        heldOn('2026-02-01', [$this->ana]);
        $foreign = WorkCenter::factory()->create();

        $this->get(route('tenant.telework.trainings.index', [$this->tenant, 'center' => $foreign->id]))->assertInertia(fn (Assert $page) => $page
            ->has('trainings.data', 1)->where('filters.center', null)->where('uncovered', null));
    });

    it('pages the roster by due date and does not repeat the grouped query', function (): void {
        foreach (range(1, 17) as $n) {
            joinedOn(CarbonImmutable::parse('2026-01-01 15:00:00')->addDays($n)->toDateTimeString(), trainee(sprintf('Persona %02d', $n)));
        }
        $this->expectsDatabaseQueryCount(QUERIES_TRAININGS);

        $this->get(route('tenant.telework.trainings.index', [$this->tenant, 'people_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 17)
            ->where('people.last_page', 2)
            ->where('people.current_page', 2)
            ->has('people.data', 2)
            ->where('people.data.0.name', 'Persona 16')
            ->where('people.data.0.dueOn', '2027-01-17')
            ->where('people.data.1.name', 'Persona 17'));
    });

    it('gives the first fifteen on page one in due order', function (): void {
        foreach (range(1, 17) as $n) {
            joinedOn(CarbonImmutable::parse('2026-01-01 15:00:00')->addDays($n)->toDateTimeString(), trainee(sprintf('Persona %02d', $n)));
        }

        $this->get(route('tenant.telework.trainings.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 15)->where('people.data.0.name', 'Persona 01')->where('people.data.14.name', 'Persona 15'));
    });

    it('paginates both lists fifteen at a time', function (): void {
        foreach (range(1, 16) as $day) {
            heldOn(CarbonImmutable::parse('2026-01-01')->addDays($day)->toDateString(), [$this->ana]);
        }

        $this->get(route('tenant.telework.trainings.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
            ->has('trainings.data', 15)->where('trainings.total', 16)->where('trainings.last_page', 2));
    });

    it('does not show another company\'s trainings and sends no attendee names', function (): void {
        heldOn('2026-02-01', [$this->ana]);
        $foreign = TrainingFactory::new()->create();

        $this->get(route('tenant.telework.trainings.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
            ->has('trainings.data', 1)->where('trainings.data.0.attendees', 1))
            ->assertDontSee('Ana Ruiz');
        expect($foreign->id)->not->toBeNull();
    });

    it('is only for those who manage telework', function (TenantRole $role, int $status): void {
        $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.telework.trainings.index', $this->tenant))->assertStatus($status);
    })->with([[TenantRole::Owner, 200], [TenantRole::Hr, 200], [TenantRole::Viewer, 403], [TenantRole::Committee, 403]]);
});
