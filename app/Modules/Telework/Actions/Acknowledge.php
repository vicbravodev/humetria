<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\AckChannel;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Support\OpenedPortal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A person confirms they read a document version they were asked for: the time and the address they confirmed from stay
 * as evidence (designer decision 6), never in a log. An employee confirms from their portal; a Comisión member in the
 * panel. A request that is not theirs does not exist; a second confirmation is refused and changes nothing.
 */
final readonly class Acknowledge
{
    /**
     * @throws ModelNotFoundException when the document was not asked of this person.
     * @throws ValidationException when it was already confirmed.
     */
    public function handle(OpenedPortal $portal, string $documentId, ?string $ip): void
    {
        $this->confirm(['employee_id' => $portal->employeeId], $documentId, AckChannel::Portal, $ip);
    }

    /**
     * @throws ModelNotFoundException when the document was not asked of this user.
     * @throws ValidationException when it was already confirmed.
     */
    public function inPanel(int $userId, string $documentId, ?string $ip): void
    {
        $this->confirm(['user_id' => $userId], $documentId, AckChannel::Panel, $ip);
    }

    /** @param array{employee_id: string}|array{user_id: int} $person */
    private function confirm(array $person, string $documentId, AckChannel $channel, ?string $ip): void
    {
        DB::transaction(function () use ($person, $documentId, $channel, $ip): void {
            $request = Acknowledgement::query()->where('document_id', $documentId)->where($person)->lockForUpdate()->firstOrFail();

            if ($request->acknowledged_at !== null) {
                throw ValidationException::withMessages(['document' => 'Ya confirmó este documento.']);
            }

            $request->update(['channel' => $channel, 'acknowledged_at' => now(), 'ip' => $ip]);

            Log::info('telework.acknowledged', ['document_id' => $documentId, ...$person, 'channel' => $channel->value]);
        });
    }
}
