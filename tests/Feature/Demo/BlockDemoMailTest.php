<?php

declare(strict_types=1);

use App\Listeners\BlockDemoMail;
use App\Modules\Campaigns\Actions\RemindNextEvaluations;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Complaints\Actions\SendComplaintDigests;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Disclosure\Actions\SendDisclosureDigests;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Engagement\Actions\AnnounceUpcomingPulses;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Interventions\Actions\SendProgramDigests;
use App\Modules\Interventions\Models\Program;
use App\Modules\Results\Actions\RemindReferrals;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Seating\Actions\RemindStandingQuestions;
use App\Modules\Seating\Actions\SendSeatingDigests;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\RemindUnansweredAccidents;
use App\Modules\Telework\Actions\SendTeleworkDigests;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\DemoMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(BlockDemoMail::class);

/** What the array mailer delivered. */
function deliveredMail(): int
{
    return count(app('mailer')->getSymfonyTransport()->messages());
}

it('cancels a message whose recipients are all demo addresses', function (): void {
    Log::spy();

    Mail::raw('Hola', fn ($message) => $message->to('ana@'.DemoMail::DOMAIN)->cc('luis@'.strtoupper(DemoMail::DOMAIN))->subject('Secreto'));

    expect(deliveredMail())->toBe(0);
    Log::shouldHaveReceived('debug')->with('demo.mail_blocked', ['recipients' => 2])->once();
});

it('lets through a message with any real recipient', function (): void {
    Log::spy();

    Mail::raw('Hola', fn ($message) => $message->to('ana@'.DemoMail::DOMAIN)->bcc('alguien@example.com'));
    Mail::raw('Hola', fn ($message) => $message->to('pedro@'.DemoMail::DOMAIN.'.example.com'));

    expect(deliveredMail())->toBe(2);
    Log::shouldNotHaveReceived('debug', ['demo.mail_blocked', Mockery::any()]);
});

it('skips the demo in the digests and reminders', function (string $action, Closure $row): void {
    $this->travelTo('2026-12-01 15:00:00');
    Notification::fake();
    Queue::fake();
    $demo = $row();
    $real = $row();
    Tenant::query()->whereKey($demo)->firstOrFail()->forceFill(['is_demo' => true])->save();

    // Every scheduled sweep enters each company it works in: the demo is never entered.
    $directory = resolve(Companies::class);
    $entered = new ArrayObject;
    app()->instance(Companies::class, new readonly class($directory, $entered) implements Companies
    {
        /** @param ArrayObject<int, string> $entered */
        public function __construct(private Companies $directory, private ArrayObject $entered) {}

        public function enter(string $tenantId): CompanySummary
        {
            $this->entered->append($tenantId);

            return $this->directory->enter($tenantId);
        }

        public function enterBySlug(string $slug): ?CompanySummary
        {
            return $this->directory->enterBySlug($slug);
        }

        public function current(): CompanySummary
        {
            return $this->directory->current();
        }

        public function contact(): ?string
        {
            return $this->directory->contact();
        }
    });

    resolve($action)->handle();

    expect($entered->getArrayCopy())->toContain($real)->not->toContain($demo);
})->with([
    'progress digests' => [SendProgressDigests::class, fn (): string => Campaign::factory()->active()->create()->tenant_id],
    'next evaluations' => [RemindNextEvaluations::class, fn (): string => Campaign::factory()->closed()->create()->tenant_id],
    'complaint digests' => [SendComplaintDigests::class, fn (): string => ComplaintChannel::factory()->active()->create()->tenant_id],
    'pulse announcements' => [AnnounceUpcomingPulses::class, fn (): string => PulseSchedule::factory()->create(['next_run_on' => '2026-12-04'])->tenant_id],
    'disclosure digests' => [SendDisclosureDigests::class, fn (): string => PageLink::factory()->create()->tenant_id],
    'program digests' => [SendProgramDigests::class, fn (): string => Program::factory()->create()->tenant_id],
    'referral reminders' => [RemindReferrals::class, fn (): string => ScreeningReferral::factory()->create(['remind_on' => '2026-11-30'])->tenant_id],
    'standing questions' => [RemindStandingQuestions::class, fn (): string => RiskAssessment::factory()->create()->tenant_id],
    'seating digests' => [SendSeatingDigests::class, fn (): string => StandingPosition::factory()->create()->tenant_id],
    'accident reminders' => [RemindUnansweredAccidents::class, fn (): string => WorkerNotice::factory()->create()->tenant_id],
    'telework digests' => [SendTeleworkDigests::class, fn (): string => Teleworker::factory()->create()->tenant_id],
]);
