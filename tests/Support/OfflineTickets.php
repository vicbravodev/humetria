<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

/**
 * Every answer after a verified signature carries a new batch of tickets and the context (criterio 3.3.1). The tickets are
 * random, so a test that pins the rest of the body exactly asks for them apart: it checks their shape and then reads the
 * answer without them, so what else the body says stays exact.
 */
final class OfflineTickets
{
    public static function without(TestResponse $response): TestResponse
    {
        $response->assertJsonStructure([
            'tickets' => [['ticket', 'issuedAt']],
            'context' => ['tenantId', 'employeeId', 'centerId', 'agreementVersion', 'rpId', 'offline'],
        ]);

        return TestResponse::fromBaseResponse(response()->json(Arr::except($response->json(), ['tickets', 'context']), $response->getStatusCode()));
    }
}
