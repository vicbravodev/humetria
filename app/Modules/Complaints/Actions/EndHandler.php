<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Actions\RecordAudit;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\ActiveHandlers;
use App\Modules\Complaints\Support\HandlerNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Ends a designation (it stays on record) and tells the other handlers. An open channel is never left without anybody to
 * handle it: the last handler cannot go until the channel is paused.
 */
final readonly class EndHandler
{
    public function __construct(private RecordAudit $audit, private ActiveHandlers $active, private HandlerNotices $notices) {}

    public function handle(ComplaintHandler $handler): void
    {
        DB::transaction(function () use ($handler): void {
            if (ComplaintChannel::query()->first()?->isOpen() === true && ! $this->othersCover($handler)) {
                throw ValidationException::withMessages(['handler' => 'El canal está abierto y esta es la única persona que lo atiende. Designa a otra o pon el canal en pausa antes.']);
            }

            $handler->update(['ended_at' => now()]);
            $this->audit->handle('complaint_handler.ended', $handler);
        });

        Log::info('complaints.handler_ended', ['handler_id' => $handler->id]);
        $this->notices->send($handler->user_id, designated: false);
    }

    private function othersCover(ComplaintHandler $handler): bool
    {
        foreach ($this->active->all() as ['handler' => $other]) {
            if ($other->id !== $handler->id) {
                return true;
            }
        }

        return false;
    }
}
