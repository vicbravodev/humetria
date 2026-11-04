<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The permanent link to the page of the workers of the current company (criterio C-55), made the first time it is needed and
 * the same afterwards: asking again never changes the link RH already printed. Only its hash is kept; the token is derived and
 * never logged nor audited.
 */
final readonly class IssuePageLink
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(int $userId): PageLink
    {
        return DB::transaction(function () use ($userId): PageLink {
            $tenantId = $this->tenant->id();

            // `createOrFirst`: if two requests race, the loser reads the winner's row instead of failing on the unique index.
            $link = PageLink::query()->createOrFirst([], [
                'tenant_id' => $tenantId,
                'version' => 1,
                'token_hash' => PageLink::hashToken(PageLink::tokenFor($tenantId, 1, config()->string('app.key'))),
                'enabled_at' => now(),
                'created_by' => $userId,
            ]);

            if ($link->wasRecentlyCreated) {
                Log::info('disclosure.page_link_issued', ['page_link_id' => $link->id]);
                $this->audit->handle('disclosure.page_link_issued', $link, ['version' => $link->version]);
            }

            return $link;
        });
    }
}
