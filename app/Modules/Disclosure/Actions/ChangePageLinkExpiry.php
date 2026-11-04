<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Disclosure\Support\Days;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Sets, or takes away, the day the link of the page stops opening (criterio C-55). The page is permanent: with no day it never
 * expires. A day is the last one it opens, through its end in the display time zone, from tomorrow and no further than
 * `disclosure.link_max_months` ahead. The dashboard warns when it is near.
 */
final readonly class ChangePageLinkExpiry
{
    public function __construct(private IssuePageLink $issue, private RecordAudit $audit) {}

    /**
     * @param  string|null  $day  Y-m-d, or null to take the expiry away.
     *
     * @throws ValidationException when the day is not after today or is further than the longest term.
     */
    public function handle(int $userId, ?string $day): PageLink
    {
        $zone = config()->string('app.display_timezone');

        if ($day !== null && ($day <= Days::today() || $day > CarbonImmutable::parse(Days::today(), $zone)->addMonthsNoOverflow(config()->integer('disclosure.link_max_months'))->toDateString())) {
            throw ValidationException::withMessages(['expires_on' => 'Elige un día desde mañana y hasta dentro de '.config()->integer('disclosure.link_max_months').' meses.']);
        }

        return DB::transaction(function () use ($userId, $day, $zone): PageLink {
            $link = PageLink::query()->whereKey($this->issue->handle($userId)->id)->lockForUpdate()->firstOrFail();
            $link->update(['expires_at' => $day === null ? null : CarbonImmutable::parse($day, $zone)->endOfDay()->utc()]);

            Log::info('disclosure.page_link_expiry_changed', ['page_link_id' => $link->id, 'expires' => $day !== null]);
            $this->audit->handle('disclosure.page_link_expiry_changed', $link, ['version' => $link->version, 'expires_on' => $day]);

            return $link;
        });
    }
}
