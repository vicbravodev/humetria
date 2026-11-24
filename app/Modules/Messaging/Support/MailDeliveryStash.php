<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * Holds the id of the mail delivery between the two listeners of one notification: OpenMailDelivery (NotificationSending) makes
 * it and StampMailDelivery (MessageSending, which knows no notification) takes it. Scoped: each job starts empty.
 */
final class MailDeliveryStash
{
    private ?string $deliveryId = null;

    public function put(string $deliveryId): void
    {
        $this->deliveryId = $deliveryId;
    }

    public function take(): ?string
    {
        $id = $this->deliveryId;
        $this->deliveryId = null;

        return $id;
    }
}
