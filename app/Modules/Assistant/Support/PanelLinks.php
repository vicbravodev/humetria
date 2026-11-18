<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Tenancy\Contracts\Companies;

/** The panel screens of the current company that Humi's answers link to, as paths. */
final readonly class PanelLinks
{
    public function __construct(private Companies $companies) {}

    public function dashboard(): string
    {
        return $this->path('tenant.dashboard');
    }

    public function workCenters(): string
    {
        return $this->path('tenant.work-centers.index');
    }

    public function telework(): string
    {
        return $this->path('tenant.telework.index');
    }

    public function teleworkCenter(string $workCenterId): string
    {
        return $this->path('tenant.telework.center', ['workCenter' => $workCenterId]);
    }

    public function seating(): string
    {
        return $this->path('tenant.seating.index');
    }

    public function vacationRequests(): string
    {
        return $this->path('tenant.vacations.requests.index');
    }

    public function complaints(): string
    {
        return $this->path('tenant.complaints.index');
    }

    public function pulse(): string
    {
        return $this->path('tenant.pulse.index');
    }

    public function climate(): string
    {
        return $this->path('tenant.climate.index');
    }

    public function attendance(): string
    {
        return $this->path('tenant.attendance.index');
    }

    public function attendanceSetup(): string
    {
        return $this->path('tenant.attendance.setup');
    }

    public function attendanceCorrections(): string
    {
        return $this->path('tenant.attendance.corrections.index');
    }

    public function orgChart(): string
    {
        return $this->path('tenant.org-chart.index');
    }

    public function orgChartQuality(): string
    {
        return $this->path('tenant.org-chart.quality');
    }

    public function compensation(): string
    {
        return $this->path('tenant.compensation.index');
    }

    public function campaigns(): string
    {
        return $this->path('tenant.campaigns.index');
    }

    public function campaign(string $campaignId): string
    {
        return $this->path('tenant.campaigns.show', ['campaign' => $campaignId]);
    }

    public function results(string $campaignId): string
    {
        return $this->path('tenant.campaigns.results', ['campaign' => $campaignId]);
    }

    public function program(string $campaignId): string
    {
        return $this->path('tenant.campaigns.program', ['campaign' => $campaignId]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function path(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->companies->current()->slug, ...$parameters], absolute: false);
    }
}
