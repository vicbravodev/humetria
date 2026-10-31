<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\SubmitChecklist;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Support\AnswerRules;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PortalPending;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

mutates(SubmitChecklist::class, AnswerRules::class);

beforeEach(function (): void {
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    $this->tenant = Tenant::factory()->create();
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $this->checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $teleworker->work_center_id]);
    // yes-favorable, no-favorable, informative, and a sensitive one; plus a Section II question that is not asked here.
    $this->q = [
        'yes' => addQuestion($this->checklist, 1, 'yes'),
        'no' => addQuestion($this->checklist, 2, 'no'),
        'info' => addQuestion($this->checklist, 3, 'informative'),
        'health' => addQuestion($this->checklist, 4, 'yes', sensitive: true),
    ];
    addQuestion($this->checklist, 5, 'yes', section: 2);
    $this->application = ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'checklist_id' => $this->checklist->id]);
    $this->portal = resolve(OpenPortal::class)->handle(resolve(IssuePortalLink::class)->handle($this->employee->id));
});

/** @return array<string, ?string> Answers that raise no flag, the sensitive one only when `$withHealth`. */
function goodAnswers(bool $withHealth = false): array
{
    $q = test()->q;

    return [$q['yes']->id => 'yes', $q['no']->id => 'no', $q['info']->id => 'yes', $q['health']->id => $withHealth ? 'yes' : null];
}

/**
 * @param  array<string, ?string>|null  $answers
 * @param  array<string, string>  $notes
 * @param  array<string, list<string>>  $photos
 */
function submit(?array $answers = null, bool $consent = false, array $notes = [], ?string $specialNeeds = null, array $photos = [], ?OpenedPortal $portal = null, ?string $application = null): void
{
    resolve(SubmitChecklist::class)->handle($portal ?? test()->portal, $application ?? test()->application->id, $consent, $answers ?? goodAnswers($consent), $notes, $specialNeeds, $photos);
}

/** @return array<string, string> The messages of the validation error a submission raises, by field. */
function refusal(Closure $attempt): array
{
    try {
        $attempt();
    } catch (ValidationException $e) {
        return array_map(fn (array $messages): string => $messages[0], $e->errors());
    }

    throw new RuntimeException('The submission was accepted.');
}

it('stores the answers and leaves the application submitted for the committee, unreviewed', function (): void {
    submit();

    $application = $this->application->fresh();
    expect($application->submitted_at)->not->toBeNull()
        ->and($application->consent_id)->toBeNull()
        ->and(ChecklistAnswer::query()->count())->toBe(4)
        ->and(ChecklistAnswer::query()->where('question_id', $this->q['health']->id)->sole()->answer)->toBeNull()
        ->and(ChecklistAnswer::query()->where('question_id', $this->q['no']->id)->sole()->answer)->toBe('no');

    $item = resolve(PortalPending::class)->for($this->portal);
    expect($item->checklists)->toBe([])->and($item->sent[0]->label)->toBe('En revisión de la Comisión');
});

it('requires an answer for every non-sensitive question', function (): void {
    $answers = goodAnswers();
    unset($answers[$this->q['no']->id]);

    expect(refusal(fn () => submit($answers)))->toHaveKey('answers.'.$this->q['no']->id);
    expect(refusal(fn () => submit([...goodAnswers(), $this->q['yes']->id => null])))->toHaveKey('answers.'.$this->q['yes']->id);
    expect(ChecklistAnswer::query()->count())->toBe(0)->and($this->application->fresh()->submitted_at)->toBeNull();
});

it('rejects answers that are not part of the checklist or not yes, no or not_applicable', function (): void {
    expect(refusal(fn () => submit([...goodAnswers(), (string) Str::uuid() => 'yes'])))->toHaveKey('answers');
    // A question of Section II is not part of a Section I application.
    $second = ChecklistQuestion::query()->where('section', 2)->sole();
    expect(refusal(fn () => submit([...goodAnswers(), $second->id => 'yes'])))->toHaveKey('answers');
    expect(refusal(fn () => submit([...goodAnswers(), $this->q['yes']->id => 'maybe'])))->toHaveKey('answers.'.$this->q['yes']->id);
    expect(refusal(fn () => submit([...goodAnswers(), $this->q['info']->id => 'informative'])))->toHaveKey('answers.'.$this->q['info']->id);
});

it('requires a risk note exactly when the answer is the unfavorable one', function (string $question, string $answer, ?string $note, bool $accepted): void {
    $id = $this->q[$question]->id;
    $attempt = fn () => submit([...goodAnswers(), $id => $answer], notes: $note === null ? [] : [$id => $note]);

    if ($accepted) {
        $attempt();
        expect(ChecklistAnswer::query()->where('question_id', $id)->sole()->risk_note)->toBe($note);
    } else {
        expect(refusal($attempt))->toHaveKey('risk_notes.'.$id);
        expect(ChecklistAnswer::query()->count())->toBe(0);
    }
})->with([
    'favorable yes, answered no, no note' => ['yes', 'no', null, false],
    'favorable yes, answered no, blank note' => ['yes', 'no', '   ', false],
    'favorable yes, answered no, with note' => ['yes', 'no', 'Hay un cable suelto', true],
    'favorable yes, answered yes, with note' => ['yes', 'yes', 'No hace falta', false],
    'favorable yes, not applicable, no note' => ['yes', 'not_applicable', null, true],
    'favorable yes, not applicable, with note' => ['yes', 'not_applicable', 'Nota', false],
    'favorable no, answered yes, no note' => ['no', 'yes', null, false],
    'favorable no, answered yes, with note' => ['no', 'yes', 'Hay mascotas', true],
    'favorable no, answered no, with note' => ['no', 'no', 'Nota', false],
    'informative, answered no, no note' => ['info', 'no', null, true],
    'informative, answered yes, with note' => ['info', 'yes', 'Nota', false],
]);

it('refuses a risk note for a question that has no answer to flag', function (): void {
    $notes = [(string) Str::uuid() => 'Nota de nadie'];

    expect(refusal(fn () => submit(notes: $notes)))->toHaveKey('risk_notes');
});

/** Another application of the same person and checklist, for a test that needs several attempts. */
function freshApplication(): string
{
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => test()->workplace->teleworker_id]);

    return ChecklistApplicationFactory::new()->create(['tenant_id' => test()->tenant->id, 'workplace_id' => $workplace->id, 'checklist_id' => test()->checklist->id])->id;
}

it('leaves sensitive questions unanswered when consent is declined', function (): void {
    submit(consent: false);

    expect($this->application->fresh()->consent_id)->toBeNull()->and($this->application->fresh()->consent_accepted_at)->toBeNull()
        ->and(ChecklistAnswer::query()->where('question_id', $this->q['health']->id)->sole()->answer)->toBeNull();
});

it('refuses sensitive answers, photos and special needs when consent is declined, and deletes the stored photos', function (): void {
    Storage::disk('telework_files')->put('photos/x.jpg', 'x');

    expect(refusal(fn () => submit([...goodAnswers(), $this->q['health']->id => 'yes'], false, application: freshApplication())))->toHaveKey('answers.'.$this->q['health']->id);
    expect(refusal(fn () => submit(null, false, [], 'Necesito silla', application: freshApplication())))->toHaveKey('special_needs');
    expect(refusal(fn () => submit(null, false, [], null, ['general' => ['photos/x.jpg']], application: freshApplication())))->toHaveKey('photos');

    Storage::disk('telework_files')->assertMissing('photos/x.jpg');
    expect(ChecklistAnswer::query()->count())->toBe(0)->and(ChecklistPhoto::query()->count())->toBe(0);
});

it('requires sensitive questions when consent is accepted and records the consent version and time', function (): void {
    $this->freezeSecond();
    expect(refusal(fn () => submit(goodAnswers(), true)))->not->toBeEmpty()->toHaveKey('answers.'.$this->q['health']->id);

    submit(goodAnswers(withHealth: true), true, specialNeeds: 'Necesito un escritorio alto');

    $application = $this->application->fresh();
    expect($application->consent_id)->toBe(resolve(App\Modules\Telework\Support\Settings::class)->currentConsent()->id)
        ->and($application->consent_accepted_at?->equalTo(now()))->toBeTrue()
        ->and($application->special_needs)->toBe('Necesito un escritorio alto')
        ->and(ChecklistAnswer::query()->where('question_id', $this->q['health']->id)->sole()->answer)->toBe('yes');
});

it('treats a blank special needs note as none', function (): void {
    submit(goodAnswers(true), true, specialNeeds: "  \n ");

    expect($this->application->fresh()->special_needs)->toBeNull();
});

it('stores the photos of the list and of a question, and deletes them when the submission fails', function (): void {
    $disk = Storage::disk('telework_files');
    $disk->put('p/general.jpg', 'x');
    $disk->put('p/question.jpg', 'x');
    $id = $this->q['yes']->id;

    submit([...goodAnswers(true), $id => 'no'], true, [$id => 'Cable suelto'], null, ['general' => ['p/general.jpg'], $id => ['p/question.jpg']]);

    expect(ChecklistPhoto::query()->whereNull('question_id')->sole()->path)->toBe('p/general.jpg')
        ->and(ChecklistPhoto::query()->where('question_id', $id)->sole()->path)->toBe('p/question.jpg');
    $disk->assertExists('p/general.jpg');

    $disk->put('p/lost.jpg', 'x');
    expect(refusal(fn () => submit(goodAnswers(true), true, photos: ['general' => ['p/lost.jpg']], application: $this->application->id)))->toHaveKey('application');
    $disk->put('p/lost2.jpg', 'x');
    expect(refusal(fn () => submit([], true, photos: ['general' => ['p/lost2.jpg']], application: freshApplication())))->toHaveKey('answers.'.$this->q['yes']->id);
    $disk->assertMissing('p/lost2.jpg');
    $disk->assertMissing('p/lost.jpg');
});

it('refuses photos for a question that is not in the checklist', function (): void {
    Storage::disk('telework_files')->put('p/a.jpg', 'x');

    expect(refusal(fn () => submit(goodAnswers(true), true, photos: [(string) Str::uuid() => ['p/a.jpg']])))->toHaveKey('photos');
    Storage::disk('telework_files')->assertMissing('p/a.jpg');
});

it('rejects a second submission of the same application and stores nothing more', function (): void {
    submit();
    $before = [ChecklistAnswer::query()->count(), ChecklistPhoto::query()->count()];
    $submittedAt = $this->application->fresh()->submitted_at;
    $this->travel(5)->minutes();
    Storage::disk('telework_files')->put('p/late.jpg', 'x');

    $errors = refusal(fn () => submit(photos: ['general' => ['p/late.jpg']]));

    expect($errors)->toBe(['application' => 'Esta lista ya se envió.'])
        ->and([ChecklistAnswer::query()->count(), ChecklistPhoto::query()->count()])->toBe($before)
        ->and($this->application->fresh()->submitted_at?->equalTo($submittedAt))->toBeTrue();
    Storage::disk('telework_files')->assertMissing('p/late.jpg');
});

it('locks the application row while it submits', function (): void {
    DB::enableQueryLog();
    submit();

    expect(collect(DB::getQueryLog())->pluck('query')->contains(fn (string $sql): bool => str_contains($sql, 'for update')))->toBeTrue();
});

it('keeps the version the application was created with when a newer one is published', function (): void {
    $v2 = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->checklist->work_center_id, 'version' => 2]);
    $extra = addQuestion($v2, 1, 'yes');

    // The answers of version 1 still fit; a question of version 2 is unknown to this application.
    expect(refusal(fn () => submit([...goodAnswers(), $extra->id => 'yes'])))->toHaveKey('answers');

    submit();

    expect($this->application->fresh()->checklist_id)->toBe($this->checklist->id)
        ->and(ChecklistAnswer::query()->pluck('question_id')->all())->each->toBeIn(array_map(fn (ChecklistQuestion $q): string => $q->id, $this->q));
});

it('answers only the application of the person whose link it is', function (): void {
    $stranger = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $stranger->id, 'work_center_id' => Teleworker::query()->firstOrFail()->work_center_id]);
    $other = resolve(OpenPortal::class)->handle(resolve(IssuePortalLink::class)->handle($stranger->id));

    expect(fn () => submit(portal: $other))->toThrow(ModelNotFoundException::class);
    expect(fn () => submit(application: (string) Str::uuid()))->toThrow(ModelNotFoundException::class);
    expect($this->application->fresh()->submitted_at)->toBeNull();
});

it('cannot reach the application of another company', function (): void {
    $theirs = Tenant::factory()->create();
    $employee = EmployeeFactory::new()->create(['tenant_id' => $theirs->id]);
    workIn($theirs);
    $application = ChecklistApplicationFactory::new()->create(['tenant_id' => $theirs->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $theirs->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $theirs->id, 'employee_id' => $employee->id])->id])->id]);
    workIn($this->tenant);

    expect(fn () => submit(application: $application->id))->toThrow(ModelNotFoundException::class);
    expect(ChecklistApplication::query()->find($application->id))->toBeNull()
        ->and(ChecklistAnswer::query()->count())->toBe(0);
});

it('keeps the answers and photos of one company out of the reach of another', function (): void {
    submit();
    $theirs = Tenant::factory()->create();
    workIn($theirs);

    expect(ChecklistAnswer::query()->count())->toBe(0)->and(ChecklistPhoto::query()->count())->toBe(0);
});

it('refuses in the database a note without an answer and a blank note', function (): void {
    $attempt = fn (array $row) => DB::transaction(fn () => ChecklistAnswer::query()->create([
        'tenant_id' => $this->tenant->id, 'application_id' => $this->application->id, 'question_id' => $this->q['yes']->id, ...$row,
    ]));

    expect(fn () => $attempt(['answer' => null, 'risk_note' => 'Nota']))->toThrow(QueryException::class);
    expect(fn () => $attempt(['answer' => 'no', 'risk_note' => '  ']))->toThrow(QueryException::class);
    expect(fn () => $attempt(['answer' => 'sí']))->toThrow(QueryException::class);
    expect($attempt(['answer' => null])->answer)->toBeNull();
});

it('never writes answers, notes or special needs to the logs or the audit trail', function (): void {
    $lines = [];
    Log::listen(function ($message) use (&$lines): void {
        $lines[] = json_encode([$message->message, $message->context], JSON_UNESCAPED_UNICODE);
    });
    $id = $this->q['yes']->id;

    submit([...goodAnswers(true), $id => 'no'], true, [$id => 'Nota secreta de riesgo'], 'Necesidad especial secreta');

    $logged = implode("\n", $lines);
    expect($logged)->toContain('telework.checklist_submitted')->toContain($this->application->id)
        ->not->toContain('secreta')->not->toContain('riesgo')->not->toContain('"no"');
    expect(json_encode(AuditEntry::query()->get()->toArray(), JSON_UNESCAPED_UNICODE))->not->toContain('secreta');
});
