<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\RemindStandingQuestions;
use App\Modules\Seating\Actions\StartAssessment;
use App\Modules\Seating\Jobs\SendStandingQuestions;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Notifications\StandingQuestions;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
 * «2 preguntas sobre su trabajo de pie» (board V3-Correos): when RH starts the assessment of a person, they are asked for
 * their footwear and discomfort through their portal link, and reminded once a week, twice at most, while the assessment
 * waits and they have not answered. Answering is voluntary.
 */

mutates(SendStandingQuestions::class, RemindStandingQuestions::class, StandingQuestions::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-02 15:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** Starts Ana's assessment; the job it queues runs only when the test runs it. */
function startAna(): RiskAssessment
{
    Queue::fake();

    return resolve(StartAssessment::class)->handle(test()->center->id, test()->ana->id, test()->hr->id);
}

function sendQuestions(): void
{
    app()->call([new SendStandingQuestions(test()->tenant->id, test()->ana->id), 'handle']);
}

it('asks the person once, after the commit, when their assessment starts', function (): void {
    startAna();
    resolve(StartAssessment::class)->handle($this->center->id, $this->ana->id, $this->hr->id);

    Queue::assertPushed(SendStandingQuestions::class, 1);
    Queue::assertPushed(SendStandingQuestions::class, fn (SendStandingQuestions $job): bool => $job->tenantId === $this->tenant->id && $job->employeeId === $this->ana->id && $job instanceof ShouldQueueAfterCommit);
});

it('emails the 2 questions, as usted, with the link to their Ley Silla page and nothing they answered', function (): void {
    Notification::fake();
    Log::spy();
    startAna();
    $token = resolve(IssuePortalLink::class)->handle($this->ana->id);

    sendQuestions();

    Notification::assertSentOnDemand(StandingQuestions::class, function (StandingQuestions $notification, array $channels, AnonymousNotifiable $notifiable) use ($token): bool {
        $mail = $notification->toMail($notifiable);
        $html = (string) $mail->render();

        return $notifiable->routes['mail'] === 'ana@acme.test'
            && $channels === ['mail']
            && $mail->subject === '2 preguntas sobre su trabajo de pie'
            && $mail->greeting === 'Hola, Ana Ruiz'
            && $mail->introLines === ['Queremos saber cómo es su calzado y si ha sentido alguna molestia al trabajar de pie. Con eso decidimos qué asiento y qué descansos le corresponden.']
            && $mail->actionText === 'Contestar ahora'
            && $mail->actionUrl === route('portal.seating.show', $token)
            && str_contains($html, 'Contestar es voluntario y toma un minuto. No tiene ninguna consecuencia si prefiere no hacerlo.')
            && str_contains($html, 'Acme</span>');
    });
    Log::shouldHaveReceived('info')->with('seating.standing_questions_sent', ['employee_id' => $this->ana->id]);
});

it('asks nothing once the person answered, no assessment waits, or they cannot be reached', function (string $case): void {
    Notification::fake();
    $assessment = startAna();
    match ($case) {
        'answered' => PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'submitted_at' => now()->addMinute()]),
        'no longer waiting' => $assessment->delete(),
        'no email' => $this->ana->forceFill(['email' => null])->save(),
        'left' => $this->ana->forceFill(['deactivated_at' => now()])->save(),
        'blocked' => Tenant::query()->whereKey($this->tenant->id)->update(['deactivated_at' => now()]),
    };

    sendQuestions();

    Notification::assertNothingSent();
})->with(['answered', 'no longer waiting', 'no email', 'left', 'blocked']);

it('still asks when what the person answered is older than the assessment', function (): void {
    Notification::fake();
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'submitted_at' => now()->subYear()]);
    startAna();

    sendQuestions();

    Notification::assertSentOnDemandTimes(StandingQuestions::class, 1);
});

it('reminds a week and two weeks after the start, while nobody answered, and never again', function (int $days, bool $reminded): void {
    startAna();
    Queue::fake();

    $this->travel($days)->days();

    expect(resolve(RemindStandingQuestions::class)->handle())->toBe($reminded ? 1 : 0);
    Queue::assertPushed(SendStandingQuestions::class, $reminded ? 1 : 0);
})->with([
    '6 days' => [6, false],
    'a week' => [7, true],
    '13 days' => [13, false],
    'two weeks' => [14, true],
    'three weeks' => [21, false],
]);

it('runs from the scheduled command and reports how many it reminded', function (): void {
    startAna();
    $this->travel(7)->days();

    $this->artisan('seating:remind-questions')->expectsOutput('Recordatorios de las 2 preguntas: 1')->assertSuccessful();
});
