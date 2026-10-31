<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\DrawReplacements;
use App\Modules\Campaigns\Actions\InviteMissing;
use App\Modules\Campaigns\Actions\OpenSurvey;
use App\Modules\Campaigns\Actions\ResendInvitations;
use App\Modules\Campaigns\Actions\SubmitSurvey;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

mutates(OpenSurvey::class, SubmitSurvey::class, ResendInvitations::class, DrawReplacements::class, InviteMissing::class, CloseCampaign::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-05 15:00:00');
    $this->campaign = Campaign::factory()->active('2026-11-14')->create(['tenant_id' => Tenant::factory()->create()->id, 'instruments' => ['nom035.guide-i']]);
    $participant = Participant::factory()->create(['campaign_id' => $this->campaign->id, 'token_hash' => Participant::hashToken('the-token')]);
    ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => 'nom035.guide-i']);
});

/**
 * Every row lock taken while `$work` runs, in order, as "table mode".
 *
 * @return list<string>
 */
function rowLocks(Closure $work): array
{
    $locks = [];
    DB::listen(function (QueryExecuted $query) use (&$locks): void {
        if (preg_match('/ from "(\w+)" .* for (update|share)$/', $query->sql, $match) === 1) {
            $locks[] = "{$match[1]} {$match[2]}";
        }
    });

    $work();

    return $locks;
}

/** Expects the next transaction to be retried up to 3 times when it deadlocks with another, and runs it for real. */
function expectDeadlockRetries(): void
{
    $real = app('db');
    $db = Mockery::mock($real);
    $db->shouldReceive('transaction')->once()->withArgs(fn (Closure $callback, int $attempts = 1): bool => $attempts === 3)
        ->andReturnUsing(fn (Closure $callback, int $attempts) => $real->transaction($callback, $attempts));
    DB::swap($db);
}

it('locks the campaign before the person when taking a guide, the order every writer of a campaign follows', function (): void {
    expect(rowLocks(fn () => resolve(SubmitSurvey::class)->handle('the-token', 'nom035.guide-i', guideOneAnswers())))
        ->toBe(['campaigns share', 'campaign_participants update']);
});

it('locks the campaign before touching its people when resending, inviting or drawing', function (Closure $write): void {
    resolve(Companies::class)->enter($this->campaign->tenant_id);

    expect(rowLocks(fn () => $write($this->campaign))[0] ?? null)->toBe('campaigns update');
})->with([
    'resend' => [fn (Campaign $campaign) => resolve(ResendInvitations::class)->handle($campaign)],
    'invite missing' => [fn (Campaign $campaign) => resolve(InviteMissing::class)->handle($campaign)],
    'draw replacements' => [fn (Campaign $campaign) => resolve(DrawReplacements::class)->handle($campaign, 'female', 0)],
]);

it('retries a write that deadlocked with another, up to 3 times', function (Closure $write): void {
    resolve(Companies::class)->enter($this->campaign->tenant_id);
    expectDeadlockRetries();

    $write($this->campaign);
})->with([
    'take a guide' => [fn () => resolve(SubmitSurvey::class)->handle('the-token', 'nom035.guide-i', guideOneAnswers())],
    'resend' => [fn (Campaign $campaign) => resolve(ResendInvitations::class)->handle($campaign)],
    'invite missing' => [fn (Campaign $campaign) => resolve(InviteMissing::class)->handle($campaign)],
    'draw replacements' => [fn (Campaign $campaign) => resolve(DrawReplacements::class)->handle($campaign, 'female', 0)],
    'close' => [fn (Campaign $campaign) => resolve(CloseCampaign::class)->handle($campaign)],
]);
