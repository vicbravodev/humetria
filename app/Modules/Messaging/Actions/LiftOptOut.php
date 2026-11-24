<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Actions\RecordAudit;
use App\Modules\Messaging\Support\RecipientHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * An ALTA or START: the person, and only the person, asks to be written to again. It deletes every opt-out of that phone, with the
 * same reach the STOP had (each company it covered, or the global one). Nothing is left to link the phone: the log and the audit
 * entry carry only how many rows went. Without an opt-out it does nothing.
 */
final class LiftOptOut
{
    public function __construct(private readonly RecipientHash $hashes, private readonly RecordAudit $audit) {}

    /** @return int how many opt-outs were deleted */
    public function handle(string $e164): int
    {
        $lifted = DB::table('messaging_opt_outs')->whereIn('recipient_hash', $this->hashes->all($e164))->delete();

        if ($lifted > 0) {
            Log::info('messaging.opt_out_lifted', ['opt_outs' => $lifted]);
            $this->audit->handle('messaging.opt_out_lifted', null, ['opt_outs' => $lifted]);
        }

        return $lifted;
    }
}
