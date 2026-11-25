<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Actions\ForgetSubscriptions;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;

/**
 * RH revoked, reissued or the person disowned a key, or they left: every phone of theirs stops getting notices, though RH has no
 * phone in hand. «Salir» and «¿No es usted?» do not come here: the app gives its own subscription up (`DELETE push`) before it closes
 * the session; if that fails for lack of network, the next notice reaches that phone with the same fixed text and no data.
 */
final readonly class ForgetOnAccessRevoked
{
    public function __construct(private ForgetSubscriptions $forget) {}

    public function handle(WorkerAccessRevoked $event): void
    {
        $this->forget->handle($event->employeeId, null, 'revoked');
    }
}
