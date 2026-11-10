<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Actions\AnnounceUpcomingPulses;
use App\Modules\Engagement\Actions\LaunchScheduledPulses;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\PulseSchedule;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

it('leaves no answered value in any log line nor in the audit trail from the scheduled launch to the close', function (): void {
    // 12:00 in Mexico City on the 15th: the schedule of the 15th is due.
    $this->travelTo('2026-11-15 18:00:00');
    Bus::fake();
    $heard = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$heard): void {
        $heard[] = $event->message.json_encode($event->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
    });

    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    $hr = memberOf($tenant, TenantRole::Hr);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->count(6)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id]);
    PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => '2026-11-15', 'created_by' => User::factory()->create()->id]);

    expect(resolve(LaunchScheduledPulses::class)->handle())->toBe(1);

    $survey = Survey::query()->sole();
    foreach (Invitation::query()->where('survey_id', $survey->id)->get() as $i => $invitation) {
        $invitation->update(['token_hash' => Invitation::hashToken("pulse-token-{$i}")]);
        $answers = [];

        foreach (Questionnaire::presentedTo($survey->items, false) as $item) {
            if ($item->scale->range() !== null) {
                $answers[$item->key] = $item->key === 'eng.recommend' ? 7 : 3;
            }
        }

        $this->get(route('engagement.survey.show', "pulse-token-{$i}"))->assertOk();
        $this->post(route('engagement.survey.submit', "pulse-token-{$i}"), ['answers' => $answers, 'minutes' => 2])->assertRedirect();
    }

    $this->actingAs($hr)->post(route('tenant.pulse.close', [$tenant, $survey]))->assertRedirect();

    $everywhere = implode("\n", $heard);
    $audit = json_encode(DB::table('audit_entries')->get(), JSON_THROW_ON_ERROR);

    expect($everywhere)->toContain('engagement.pulse_launched')->toContain('engagement.response_recorded')
        ->not->toContain('eng.recommend')->not->toContain('eng.pride')->not->toContain('loop.action')
        ->and($audit)->not->toContain('eng.recommend')->not->toContain('eng.pride');
    expect(Survey::query()->sole()->status->value)->toBe('closed');
});

it('writes a log line when the heads-up goes out, without anyone\'s address', function (): void {
    $this->travelTo('2026-11-15 18:00:00');
    Notification::fake();
    Log::spy();
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $owner = memberOf($tenant, TenantRole::Owner);
    PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => '2026-11-18']);

    resolve(AnnounceUpcomingPulses::class)->handle();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.pulse_announced'
        && $context['recipients'] === 1
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $owner->email))->once();
});

it('says nothing in the pulse screens about "eNPS", an always-open pulse or a promise of secrecy', function (): void {
    $files = [
        ...File::allFiles(resource_path('js/components/engagement/pulse')),
        ...File::allFiles(resource_path('js/components/engagement/results')),
        resource_path('js/pages/engagement/trend.tsx'),
        base_path('app/Modules/Engagement/Notifications/PulseUpcoming.php'),
    ];

    foreach ($files as $file) {
        $text = mb_strtolower(File::get($file instanceof SplFileInfo ? $file->getPathname() : $file));

        foreach (['enps', 'net promoter', 'siempre abierto', 'nadie sabrá', 'nadie sabra', '100% anónim', '100 % anónim'] as $forbidden) {
            expect($text)->not->toContain($forbidden, "{$forbidden} appears in ".($file instanceof SplFileInfo ? $file->getPathname() : $file));
        }
    }
});
