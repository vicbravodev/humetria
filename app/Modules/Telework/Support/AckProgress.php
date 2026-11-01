<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;

/**
 * How many of the people a document is for today have confirmed it. Someone who left no longer counts on either side;
 * someone who arrived without a request still counts as expected.
 */
final readonly class AckProgress
{
    public function __construct(private AckAudience $audience) {}

    /**
     * The progress of every document of a page in a fixed number of queries: the audiences are read together and the
     * confirmations in one query, then counted against each document's audience.
     *
     * @param  list<TeleworkDocument>  $documents
     * @return array<string, array{acknowledged: int, expected: int}> by document id
     */
    public function forDocuments(array $documents): array
    {
        $audiences = $this->audience->ofMany($documents);
        $done = [];

        foreach (Acknowledgement::query()->whereIn('document_id', array_keys($audiences))->whereNotNull('acknowledged_at')->get(['document_id', 'employee_id', 'user_id']) as $ack) {
            $done[$ack->document_id][] = $ack;
        }

        return $this->count($audiences, $done);
    }

    /**
     * @param  array<string, AckAudienceIds>  $audiences
     * @param  array<string, list<Acknowledgement>>  $done
     * @return array<string, array{acknowledged: int, expected: int}>
     */
    private function count(array $audiences, array $done): array
    {
        $progress = [];

        foreach ($audiences as $documentId => $ids) {
            $employees = array_flip($ids->employeeIds);
            $users = array_flip($ids->userIds);
            $acknowledged = count(array_filter($done[$documentId] ?? [], fn (Acknowledgement $ack): bool => ($ack->employee_id !== null && isset($employees[$ack->employee_id])) || ($ack->user_id !== null && isset($users[$ack->user_id]))));
            $progress[$documentId] = ['acknowledged' => $acknowledged, 'expected' => $ids->expected()];
        }

        return $progress;
    }

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
