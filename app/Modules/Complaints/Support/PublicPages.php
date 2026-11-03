<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintCategory;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Enums\RespondentKind;

/** What the public pages of the channel show: only what the company chose to tell, never anything about a case. */
final readonly class PublicPages
{
    public function __construct(private TicketSigner $tickets, private HandlerCoverage $coverage) {}

    /**
     * @return array<string, mixed>
     */
    public function form(OpenedChannel $opened): array
    {
        $counts = $this->coverage->counts();

        return [
            'code' => $opened->channel->access_code,
            'ticket' => $this->tickets->issue($opened->channel->access_code),
            'today' => Dates::today()->toDateString(),
            'descriptionLimits' => ['min' => config()->integer('complaints.description.min'), 'max' => config()->integer('complaints.description.max')],
            'types' => array_map(fn (ComplaintType $type): array => ['value' => $type->value, 'label' => $type->label(), 'hint' => $type->hint()], ComplaintType::cases()),
            'kinds' => array_map(fn (RespondentKind $kind): array => ['value' => $kind->value, 'label' => $kind->label(), 'available' => $counts[$kind->value] > 0], RespondentKind::cases()),
            'categories' => array_map(fn (ComplaintCategory $category): array => ['value' => $category->value, 'label' => $category->label(), 'type' => $category->type()->value], ComplaintCategory::cases()),
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
