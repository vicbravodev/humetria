<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use DateTimeInterface;
use Illuminate\Database\Query\Expression;

/**
 * The figures at the top of the console, from what a request already keeps: how many wait, how soon the team answers
 * (median, of the last 90 days), how many became a company, and the source most came from (its utm, else the host that
 * sent it; a request without either is "no data", which still counts in the share).
 */
final readonly class DemoMetrics
{
    private const int DAYS = 90;

    public function __construct(private DemoLeads $leads) {}

    /**
     * @return array{new: int, unattended: int, first_response_hours: float|null, conversion: int|null, source: array{label: string, share: int}|null}
     */
    public function handle(): array
    {
        $since = now()->subDays(self::DAYS);
        $waiting = $this->leads->waiting();

        $window = DemoRequest::query()->toBase()->where('created_at', '>=', $since)->selectRaw(
            'count(*) as total, count(*) filter (where status = ?) as converted, '
            .'percentile_cont(0.5) within group (order by extract(epoch from handled_at - created_at)) filter (where handled_at is not null) as median',
            [DemoStatus::Converted->value],
        )->first();
        $total = is_numeric($window?->total) ? (int) $window->total : 0;
        $converted = is_numeric($window?->converted) ? (int) $window->converted : 0;

        return [
            'new' => $waiting->new,
            'unattended' => $waiting->unattended,
            'first_response_hours' => is_numeric($window?->median) ? round((float) $window->median / 3600, 1) : null,
            'conversion' => $total === 0 ? null : (int) round($converted * 100 / $total),
            'source' => $total === 0 ? null : $this->source($since, $total),
        ];
    }

    /** @return array{label: string, share: int}|null */
    private function source(DateTimeInterface $since, int $total): ?array
    {
        $source = new Expression('coalesce(utm_source, referrer_host)');
        $top = DemoRequest::query()->toBase()
            ->where('created_at', '>=', $since)
            ->whereNotNull($source)
            ->select([new Expression('coalesce(utm_source, referrer_host) as label'), new Expression('count(*) as total')])
            ->groupBy($source)
            ->orderByDesc('total')
            ->orderBy('label')
            ->first();

        return is_string($top?->label) && is_numeric($top->total)
            ? ['label' => $top->label, 'share' => (int) round((int) $top->total * 100 / $total)]
            : null;
    }
}
