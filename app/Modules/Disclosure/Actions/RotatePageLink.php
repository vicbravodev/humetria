<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Replaces the link of the page (criterio C-55), usually because it leaked to people who no longer work there: the version
 * moves, the old token stops opening at once and a new one is derived. The posters already printed have to be replaced. With no
 * link yet, the first one is simply issued.
 */
final readonly class RotatePageLink
{
    public function __construct(private IssuePageLink $issue, private RecordAudit $audit) {}

    public function handle(int $userId): PageLink
    {
        return DB::transaction(function () use ($userId): PageLink {
            $existing = PageLink::query()->first(['id']);

            if ($existing === null) {
                return $this->issue->handle($userId);
            }

            $link = PageLink::query()->whereKey($existing->id)->lockForUpdate()->firstOrFail();
            $version = $link->version + 1;
            $link->update([
                'version' => $version,
                'token_hash' => PageLink::hashToken(PageLink::tokenFor($link->tenant_id, $version, config()->string('app.key'))),
                'rotated_at' => now(),
            ]);

            Log::info('disclosure.page_link_rotated', ['page_link_id' => $link->id, 'version' => $version]);
            $this->audit->handle('disclosure.page_link_rotated', $link, ['version' => $version]);

            return $link;
        });
    }
}
