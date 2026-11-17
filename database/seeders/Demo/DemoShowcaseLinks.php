<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Actions\ReissueKioskDevice;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/** The demo's links the room shares live, found the way the story built them. */
final readonly class DemoShowcaseLinks implements DemoShowcase
{
    public function __construct(private DemoCompanies $demos, private Companies $companies, private ReissueKioskDevice $reissue, private PermissionRegistrar $permissions, private RecordAudit $audit) {}

    public function complaintChannelUrl(): ?string
    {
        // The channel's code is stable (see ComplaintsStory::accessCode()): no need to read the company to know it.
        return $this->demos->id() === null ? null : route('complaints.public.show', ComplaintsStory::accessCode());
    }

    public function kioskLink(): ?string
    {
        $tenantId = $this->demos->id();

        if ($tenantId === null) {
            return null;
        }

        // Inside the demo company (a screen of any other company is out of reach), and only for this: the console's request
        // must not go on as the demo, or its pages would carry the demo's mark. Entering also sets Spatie's team, which
        // Context::scope does not restore: it is put back by hand.
        $link = null;
        $team = $this->permissions->getPermissionsTeamId();

        try {
            Context::scope(function () use ($tenantId, &$link): void {
                $company = $this->companies->enter($tenantId);
                $kiosk = KioskDevice::query()->whereNull('revoked_at')->latest('created_at')->first();
                $token = $kiosk === null ? null : $this->reissue->handle($kiosk->id);

                if ($kiosk !== null && $token !== null) {
                    // In the demo company's trail: who issued a new screen link, never the link.
                    $this->audit->handle('demo.kiosk_reissued', $kiosk);
                    $link = route('attendance.device.kiosk.activate', [$company->slug, $token]);
                }
            });
        } finally {
            $this->permissions->setPermissionsTeamId($team);
        }

        return $link;
    }
}
