<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Exceptions\ChannelUnavailable;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\OpenedChannel;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * Finds the channel a code belongs to and works inside its company from then on. The code is the credential of the company:
 * one that is unknown, malformed, of a channel never activated or of a company that may not be used gives the same answer.
 * A paused channel is found (the page says so); taking its complaints is up to the caller.
 */
final readonly class OpenChannel
{
    public function __construct(private Companies $companies) {}

    /**
     * @throws ChannelUnavailable
     */
    public function handle(string $code): OpenedChannel
    {
        $code = strtoupper(trim($code));
        $channel = preg_match('/^[0-9A-HJKMNP-TV-Z]{16}$/', $code) === 1
            ? ComplaintChannel::query()->withoutGlobalScopes()->where('access_code', $code)->whereNotNull('enabled_at')->first()
            : null;

        if ($channel === null) {
            throw new ChannelUnavailable;
        }

        $company = $this->companies->enter($channel->tenant_id);

        if ($company->blocked) {
            throw new ChannelUnavailable;
        }

        return new OpenedChannel($channel, $company->tradeName);
    }
}
