<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Contracts\DeliveryOutcomes;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;

final class DeliveryLedger implements DeliveryOutcomes
{
    public function undelivered(array $deliveryIds): array
    {
        if ($deliveryIds === []) {
            return [];
        }

        $undelivered = [];

        // In blocks: a month of a big company is many ids.
        foreach (array_chunk($deliveryIds, 1000) as $block) {
            $found = Delivery::query()->toBase()->whereIn('id', $block)->whereNotIn('status', [DeliveryStatus::Delivered->value, DeliveryStatus::Read->value])->pluck('id');

            foreach ($found as $id) {
                if (is_string($id)) {
                    $undelivered[] = $id;
                }
            }
        }

        return $undelivered;
    }
}
