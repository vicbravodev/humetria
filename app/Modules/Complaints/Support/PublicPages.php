<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/** What the public pages of the channel show: only what the company chose to tell, never anything about a case. */
final readonly class PublicPages
{
    /**
     * @return array<string, mixed>
     */
    public function form(OpenedChannel $opened): array
    {
        return [
            'company' => $opened->company,
            'procedure' => $opened->channel->procedure,
            'acknowledgeWithinDays' => $opened->channel->acknowledge_within_days,
            'resolveWithinDays' => $opened->channel->resolve_within_days,
            'alternative' => $this->alternative(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paused(OpenedChannel $opened): array
    {
        return ['reason' => 'paused', 'company' => $opened->company, 'alternative' => $this->alternative()];
    }

    /**
     * @return array<string, mixed>
     */
    public function unavailable(): array
    {
        return ['reason' => 'invalid', 'company' => null, 'alternative' => $this->alternative()];
    }

    /**
     * Where else to go: the federal labor inspection, which takes anonymous reports with the company's data.
     *
     * @return array{name: string, email: string, phone: string, note: string}
     */
    private function alternative(): array
    {
        return [
            'name' => config()->string('complaints.external_alternative.name'),
            'email' => config()->string('complaints.external_alternative.email'),
            'phone' => config()->string('complaints.external_alternative.phone'),
            'note' => config()->string('complaints.external_alternative.note'),
        ];
    }
}
