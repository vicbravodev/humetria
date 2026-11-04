<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Switches the page of the workers on again (criterio C-55), with the same link. Switching on what is already on changes
 * nothing.
 */
final readonly class EnablePageLink
{
    public function __construct(private IssuePageLink $issue, private RecordAudit $audit) {}

    public function handle(int $userId): PageLink
    {
        return DB::transaction(function () use ($userId): PageLink {
            $link = PageLink::query()->whereKey($this->issue->handle($userId)->id)->lockForUpdate()->firstOrFail();

            if ($link->disabled_at !== null) {
                $link->update(['disabled_at' => null]);
                Log::info('disclosure.page_link_enabled', ['page_link_id' => $link->id]);
                $this->audit->handle('disclosure.page_link_enabled', $link, ['version' => $link->version]);
            }

            return $link;
        });
    }
}
