<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Carbon\CarbonImmutable;

/**
 * What the room shows: the demo company, the run preparing it, how old it is, its complaint channel and the door. Counts and
 * links only: no person of the demo.
 */
final readonly class DemoRoomPage
{
    /**
     * Older than this, the story goes stale in front of an audience: a complaint already overdue, pending leave requests
     * timing out, open surveys about to close.
     */
    public const int FRESH_DAYS = 2;

    public function __construct(private DemoCompanies $demos, private CompanyRegistry $companies, private DemoShowcase $showcase, private DoorControl $door, private QrCode $qr) {}

    /** @return array<string, mixed> */
    public function props(): array
    {
        $id = $this->demos->id();
        $company = $id === null ? null : $this->companies->find($id);
        $run = DemoRun::current();
        $channel = $company === null ? null : $this->showcase->complaintChannelUrl();

        return [
            'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->tradeName],
            'run' => $run === null ? null : ['kind' => $run->kind, 'status' => $run->status, 'step' => $run->step, 'error_class' => $run->error_class],
            'prepared' => $company === null ? null : $this->prepared(),
            'complaints' => $channel === null ? null : ['url' => $channel, 'qr' => $this->qr->dataUri($channel)],
            'door' => $this->door(),
        ];
    }

    /** @return array{ago: string, at: string, stale: bool}|null */
    private function prepared(): ?array
    {
        $at = DemoRun::query()->where('status', 'finished')->latest('finished_at')->first()?->finished_at;

        if ($at === null) {
            return null;
        }

        return [
            'ago' => $at->diffForHumans(),
            // Carbon's Spanish months end in a dot ("nov."); the design system writes "16 nov 2026".
            'at' => str_replace('.', '', $at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm')),
            'stale' => $at->lt(now()->subDays(self::FRESH_DAYS)),
        ];
    }

    /** @return array{open: bool, closes_at: ?string, claimed: int, free: int, total: int} */
    private function door(): array
    {
        $state = $this->door->state();
        $expiresAt = $state['expires_at'];

        return [
            'open' => $state['open'],
            'closes_at' => $expiresAt === null ? null : CarbonImmutable::parse($expiresAt)->setTimezone(config()->string('app.display_timezone'))->format('H:i'),
            'claimed' => $state['claimed'],
            'free' => $state['free'],
            'total' => $state['total'],
        ];
    }
}
