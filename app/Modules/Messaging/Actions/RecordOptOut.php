<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Support\RecipientHash;
use Illuminate\Support\Facades\Log;

/**
 * A STOP (rule 6): the phone asked not to be written to. It is recorded for every company that ever wrote to it (while the
 * deliveries are kept) or, when none did, for all. Neither the phone nor its hash is logged. The confirmation text is queued by the caller (HandleMetaEvents).
 */
final class RecordOptOut
{
    public function __construct(private readonly RecipientHash $hashes) {}

    /** @return int how many opt-outs were newly written */
    public function handle(string $e164, string $source): int
    {
        ['hash' => $hash, 'version' => $version] = $this->hashes->current($e164);

        /** @var list<string> $tenantIds */
        $tenantIds = Delivery::query()
            ->where('channel', 'whatsapp')
            ->whereIn('recipient_hash', $this->hashes->all($e164))
            ->distinct()
            ->pluck('tenant_id')
            ->all();

        $global = $tenantIds === [];
        $written = 0;

        foreach ($global ? [null] : $tenantIds as $tenantId) {
            $row = OptOut::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'recipient_hash' => $hash],
                ['key_version' => $version, 'source' => $source],
            );

            $written += $row->wasRecentlyCreated ? 1 : 0;
        }

        Log::info('whatsapp.opt_out_recorded', ['companies' => count($tenantIds), 'global' => $global]);

        return $written;
    }
}
