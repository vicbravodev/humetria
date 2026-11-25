<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\PlatformDeliveries;
use App\Modules\Messaging\Data\ChannelTraffic;
use App\Modules\Messaging\Data\CompanyRejections;
use App\Modules\Messaging\Data\OptOutTally;
use App\Modules\Messaging\Data\TemplateTraffic;
use App\Modules\Messaging\Data\WhatsAppConnection;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Models\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;

/** Grouped queries over the messages of every company: only counts leave them. */
final readonly class PlatformTraffic implements PlatformDeliveries
{
    private const array CHANNELS = ['mail', 'whatsapp'];

    /** Rule 10: a company is named from five rejections up. */
    private const int MINIMUM = 5;

    public function __construct(private TemplateRegistry $templates) {}

    public function byChannel(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->tallied($from, $to, 'channel');
        $traffic = [];

        foreach (self::CHANNELS as $channel) {
            $row = $rows[$channel] ?? null;

            if ($row !== null) {
                $traffic[] = new ChannelTraffic($channel, $row['total'], $row['delivered'], $row['read'], $row['rejected']);
            }
        }

        return $traffic;
    }

    public function byTemplate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->tallied($from, $to, 'template', fn (Builder $query): Builder => $query->where('channel', 'whatsapp'));
        $traffic = [];

        foreach ($this->templates->all() as $key => $template) {
            $row = $rows[$key] ?? ['total' => 0, 'delivered' => 0, 'read' => 0, 'rejected' => 0];
            $pending = $template instanceof TemplateDefinition && $template->isPending();
            $traffic[] = new TemplateTraffic($key, $template->category->value, $pending, $row['total'], $row['delivered'], $row['rejected']);
        }

        return $traffic;
    }

    public function mostRejected(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        return array_values(Delivery::query()->toBase()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->whereIn('status', DeliveryStatus::undelivered())
            ->groupBy('tenant_id')
            ->havingRaw('count(*) >= ?', [self::MINIMUM])
            ->selectRaw('tenant_id, count(*) as rejected')
            ->orderByDesc('rejected')
            ->orderBy('tenant_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): CompanyRejections => new CompanyRejections(self::text($row->tenant_id ?? null), self::count($row->rejected ?? null)))
            ->all());
    }

    public function lastWebhookAt(string $provider): ?CarbonImmutable
    {
        return WebhookEvent::query()->where('provider', $provider)->latest('received_at')->first()?->received_at;
    }

    public function connection(): WhatsAppConnection
    {
        $pause = Pause::query()->whereNull('ended_at')->first();

        return new WhatsAppConnection(
            config()->boolean('whatsapp.enabled'),
            config()->boolean('whatsapp.paused'),
            $pause?->reason,
            $pause?->started_at,
        );
    }

    public function optOuts(): OptOutTally
    {
        $row = OptOut::query()->toBase()->selectRaw(
            'count(*) filter (where tenant_id is null) as every_company, count(*) filter (where tenant_id is not null) as one_company, count(*) filter (where created_at >= ?) as recent',
            [now()->subDays(30)],
        )->first();

        return new OptOutTally(self::count($row->every_company ?? null), self::count($row->one_company ?? null), self::count($row->recent ?? null));
    }

    /**
     * The messages created in [from, to), counted by one column.
     *
     * @param  'channel'|'template'  $column
     * @param  (callable(Builder): Builder)|null  $scope
     * @return array<string, array{total: int, delivered: int, read: int, rejected: int}>
     */
    private function tallied(CarbonImmutable $from, CarbonImmutable $to, string $column, ?callable $scope = null): array
    {
        $query = Delivery::query()->toBase()->where('created_at', '>=', $from)->where('created_at', '<', $to)->whereNotNull($column);
        $rows = ($scope === null ? $query : $scope($query))
            ->groupBy($column)
            ->selectRaw(
                "{$column} as grouped, count(*) as total, count(*) filter (where status in (?, ?)) as delivered, count(*) filter (where status = ?) as read, count(*) filter (where status in (?, ?, ?, ?)) as rejected",
                [DeliveryStatus::Delivered->value, DeliveryStatus::Read->value, DeliveryStatus::Read->value, ...DeliveryStatus::undelivered()],
            )
            ->get();

        $tallied = [];

        foreach ($rows as $row) {
            $tallied[self::text($row->grouped ?? null)] = [
                'total' => self::count($row->total ?? null),
                'delivered' => self::count($row->delivered ?? null),
                'read' => self::count($row->read ?? null),
                'rejected' => self::count($row->rejected ?? null),
            ];
        }

        return $tallied;
    }

    private static function count(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
