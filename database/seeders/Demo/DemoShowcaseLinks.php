<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Actions\ReissueKioskDevice;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Actions\CreateAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/** The demo's links the room shares live, found the way the story built them. */
final readonly class DemoShowcaseLinks implements DemoShowcase
{
    public function __construct(
        private DemoCompanies $demos,
        private Companies $companies,
        private ReissueKioskDevice $reissue,
        private PermissionRegistrar $permissions,
        private RecordAudit $audit,
        private Roster $roster,
        private CreateAccessLink $links,
    ) {}

    public function complaintChannelUrl(): ?string
    {
        // The channel's code is stable (see ComplaintsStory::accessCode()): no need to read the company to know it.
        return $this->demos->id() === null ? null : route('complaints.public.show', ComplaintsStory::accessCode());
    }

    public function kioskLinks(): array
    {
        $tenantId = $this->demos->id();

        if ($tenantId === null) {
            return [];
        }

        // Inside the demo company (a screen of any other company is out of reach), and only for this: the console's request
        // must not go on as the demo, or its pages would carry the demo's mark. Entering also sets Spatie's team, which
        // Context::scope does not restore: it is put back by hand.
        $links = [];
        $team = $this->permissions->getPermissionsTeamId();

        try {
            Context::scope(function () use ($tenantId, &$links): void {
                $company = $this->companies->enter($tenantId);
                $kiosks = KioskDevice::query()->whereNull('revoked_at')->get()->keyBy('work_center_id');

                foreach (WorkCenter::query()->orderBy('name')->get() as $center) {
                    $kiosk = $kiosks->get($center->id);
                    $token = $kiosk === null ? null : $this->reissue->handle($kiosk->id);

                    if ($kiosk !== null && $token !== null) {
                        // In the demo company's trail: who issued a new screen link, never the link.
                        $this->audit->handle('demo.kiosk_reissued', $kiosk);
                        $links[] = ['center' => $center->name, 'url' => route('attendance.device.kiosk.activate', [$company->slug, $token])];
                    }
                }
            });
        } finally {
            $this->permissions->setPermissionsTeamId($team);
        }

        return $links;
    }

    public function portalLinkFor(string $employeeId): ?string
    {
        $tenantId = $this->demos->id();
        $center = DemoSession::active()?->work_center_id;

        if ($tenantId === null || $center === null) {
            return null;
        }

        // The presenter works in the demo already (impersonating it); entering it makes sure nothing is issued elsewhere.
        $this->companies->enter($tenantId);

        if (! in_array($employeeId, $this->roster->activeIdsIn($center), true)) {
            return null;
        }

        // Never mailed: the presenter opens it right here. CreateAccessLink logs the link's id, never its token; the demo's
        // trail says who issued it, as IssueAccessLink's does.
        [$link, $url] = $this->links->handle($employeeId, LinkKind::Issued, DemoRoster::hrHeadUserId());
        $this->audit->handle('demo.portal_link_issued', $link, ['employee_id' => $employeeId]);

        return $url;
    }
}
