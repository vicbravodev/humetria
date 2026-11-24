<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Support\RecipientHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A data request of a person (ARCO): every message written to their phone, in every company, goes with its history. The opt-out
 * stays (forgetting someone is not writing to them again). Mail rows keep no address or hash, so they cannot be tied to anyone and
 * go with the retention. The phone is looked up by hash and is never logged.
 */
final class ForgetRecipient
{
    public function __construct(private readonly RecipientHash $hashes) {}

    /** @return int how many messages it deleted */
    public function handle(string $e164): int
    {
        $deleted = DB::transaction(function () use ($e164): int {
            DB::statement("SET LOCAL messaging.purge = 'on'");

            $count = Delivery::query()->toBase()->where('channel', 'whatsapp')->whereIn('recipient_hash', $this->hashes->all($e164))->delete();

            DB::statement("SET LOCAL messaging.purge = 'off'");

            return $count;
        });

        Log::info('messaging.recipient_forgotten', ['deleted' => $deleted]);

        return $deleted;
    }
}
