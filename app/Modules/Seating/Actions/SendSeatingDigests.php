<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Notifications\SeatingDigest;
use App\Modules\Seating\Support\SeatingAlerts;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * The daily Ley Silla email to the owner (RH) and to each Comisión member: counts of what waits for them, in lines that
 * appear only when they are not zero. The owner sees every center with standing positions; a Comisión member the centers they
 * sit on. Each recipient gets at most one a day (`seating_digest_deliveries`, written in the same transaction as the dispatch)
 * and none when everything is quiet. Companies that may not be used get nothing.
 */
final readonly class SendSeatingDigests
{
    /** What each kind of recipient is told about: the Comisión is not told about its own absence nor the regulation. */
    private const array OWNER = ['unassessed', 'reassessment', 'reports', 'pauses', 'regulation', 'signage', 'committee'];

    private const array MEMBER = ['unassessed', 'reassessment', 'reports', 'pauses', 'signage'];

    public function __construct(private Companies $companies, private Members $members, private Committee $committee, private SeatingAlerts $alerts) {}

    /**
     * @return int how many digests were sent.
     */
    public function handle(): int
    {
        $sent = ['owner' => 0, 'committee' => 0];

        foreach (StandingPosition::query()->withoutGlobalScopes()->distinct()->orderBy('tenant_id')->get(['tenant_id'])->map(fn (StandingPosition $position): string => $position->tenant_id) as $tenantId) {
            try {
                // Each in its own company, as the system.
                Context::scope(function () use ($tenantId, &$sent): void {
                    $company = $this->companies->enter($tenantId);

                    if (! $company->blocked) {
                        $counts = $this->digest($company->tradeName, $company->slug);
                        $sent['owner'] += $counts['owner'];
                        $sent['committee'] += $counts['committee'];
                    }
                }, ['user_id' => null]);
            } catch (Throwable $failure) {
                // One company's failure never keeps the others' digests from going out.
                report($failure);
            }
        }

        Log::info('seating.digests_sent', $sent);

        return $sent['owner'] + $sent['committee'];
    }

    /**
     * @return array{owner: int, committee: int}
     */
    private function digest(string $company, string $slug): array
    {
        $sent = ['owner' => 0, 'committee' => 0];
        $centers = array_values(StandingPosition::query()->distinct()->get(['work_center_id'])->map(fn (StandingPosition $position): string => $position->work_center_id)->all());
        $owner = $this->companies->contact();

        if ($owner !== null) {
            $sent['owner'] = $this->deliver('owner', $owner, $company, null, self::OWNER, $centers, $slug);
        }

        foreach ($this->members->reviewers() as $member) {
            $mine = array_values(array_intersect($centers, $this->committee->workCenterIds($member->userId)));

            if ($mine !== []) {
                $sent['committee'] += $this->deliver("user:{$member->userId}", $member->email, $company, $member->name, self::MEMBER, $mine, $slug);
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $sections
     * @param  list<string>  $centers
     * @return int 1 when the digest went, 0 when there was nothing to say or it already went today.
     */
    private function deliver(string $recipient, string $email, string $company, ?string $name, array $sections, array $centers, string $slug): int
    {
        $lines = $this->lines($sections, $centers);

        if ($lines === []) {
            return 0;
        }

        $now = CarbonImmutable::now();
        $today = $now->setTimezone(config()->string('app.display_timezone'))->toDateString();

        return DB::transaction(function () use ($recipient, $email, $company, $name, $lines, $centers, $slug, $today, $now): int {
            // The unique key settles two runs racing: only the one that wrote the row sends.
            $written = DB::table('seating_digest_deliveries')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->companies->current()->id,
                'recipient' => $recipient,
                'day' => $today,
                'delivered_at' => $now,
            ]);

            if ($written === 0) {
                return 0;
            }

            Notification::route('mail', $email)->notify(new SeatingDigest($company, $name, $lines, count($centers) === 1 ? route('tenant.seating.center', [$slug, $centers[0]]) : route('tenant.seating.index', $slug)));

            return 1;
        });
    }

    /**
     * @param  list<string>  $sections
     * @param  list<string>  $centers
     * @return list<string>
     */
    private function lines(array $sections, array $centers): array
    {
        $totals = array_fill_keys(self::OWNER, 0);

        foreach ($centers as $center) {
            foreach ($this->alerts->for($center) as $key => $count) {
                $totals[$key] += $count;
            }
        }

        $texts = [
            'unassessed' => '{1} :count persona que trabaja de pie sin evaluar.|[2,*] :count personas que trabajan de pie sin evaluar.',
            'reassessment' => '{1} :count evaluación por repetir.|[2,*] :count evaluaciones por repetir.',
            'reports' => '{1} :count reporte de asiento abierto hace más de :days días.|[2,*] :count reportes de asiento abiertos hace más de :days días.',
            'pauses' => '{1} :count puesto sin acuerdo de pausas.|[2,*] :count puestos sin acuerdo de pausas.',
            'regulation' => '{1} :count reglamento sin las tres partes o sin depósito.|[2,*] :count reglamentos sin las tres partes o sin depósito.',
            'signage' => '{1} :count asiento fuera del puesto sin señalizar.|[2,*] :count asientos fuera del puesto sin señalizar.',
            'committee' => '{1} :count centro sin integrantes en la Comisión.|[2,*] :count centros sin integrantes en la Comisión.',
        ];
        $lines = [];

        foreach ($sections as $section) {
            if ($totals[$section] > 0) {
                $lines[] = trans_choice($texts[$section], $totals[$section], ['days' => config()->integer('seating.report_alert_days')]);
            }
        }

        return $lines;
    }
}
