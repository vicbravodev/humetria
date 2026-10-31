<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\TeleworkDocument;

/**
 * How many of the people a document is for today have confirmed it. Someone who left no longer counts on either side;
 * someone who arrived without a request still counts as expected.
 */
final readonly class AckProgress
{
    public function __construct(private AckAudience $audience) {}

    /** @return array{acknowledged: int, expected: int} */
    public function of(TeleworkDocument $document, ?AckAudienceIds $audience = null): array
    {
        $ids = $audience ?? $this->audience->of($document);

        return [
            'acknowledged' => $this->audience->requests($document, $ids)->whereNotNull('acknowledged_at')->count(),
            'expected' => $ids->expected(),
        ];
    }
}
