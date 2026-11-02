<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Tenancy\Contracts\Companies;

/** The panel screens of the current company that Humi's answers link to, as paths. */
final readonly class PanelLinks
{
    public function __construct(private Companies $companies) {}

    public function campaigns(): string
    {
        return $this->path('tenant.campaigns.index');
    }

    public function campaign(string $campaignId): string
    {
        return $this->path('tenant.campaigns.show', ['campaign' => $campaignId]);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function path(string $name, array $parameters = []): string
    {
        return route($name, ['tenant' => $this->companies->current()->slug, ...$parameters], absolute: false);
    }
}
