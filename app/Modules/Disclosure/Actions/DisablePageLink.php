<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Switches the page of the workers off without changing its link (criterio C-55): the poster stays up and opens again when RH
 * switches it on. Switching off what is already off changes nothing.
 */
final readonly class DisablePageLink
{
    public function __construct(private IssuePageLink $issue, private RecordAudit $audit) {}

    public function handle(int $userId): PageLink
    {
        return DB::transaction(function () use ($userId): PageLink {
            $link = PageLink::query()->whereKey($this->issue->handle($userId)->id)->lockForUpdate()->firstOrFail();

            if ($link->disabled_at === null) {
                $link->update(['disabled_at' => now()]);
                Log::info('disclosure.page_link_disabled', ['page_link_id' => $link->id]);
                $this->audit->handle('disclosure.page_link_disabled', $link, ['version' => $link->version]);
            }

            return $link;
        });
    }
}
