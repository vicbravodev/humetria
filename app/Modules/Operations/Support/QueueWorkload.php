<?php

declare(strict_types=1);

namespace App\Modules\Operations\Support;

use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Throwable;

/** What Horizon says of the queues, read from Redis as `horizon:status` and its dashboard do. */
final readonly class QueueWorkload
{
    public function __construct(private MasterSupervisorRepository $masters, private WorkloadRepository $workload) {}

    /**
     * @return array{status: 'running'|'paused'|'inactive'|'unreachable', waiting: int, queues: list<array{name: string, length: int, wait: int}>}
     */
    public function handle(): array
    {
        try {
            $masters = $this->masters->all();
            $workload = $this->workload->get();
        } catch (Throwable $exception) {
            // Redis down is what this page is for: it says so instead of failing.
            Log::warning('operations.horizon_unreachable', ['exception' => $exception::class]);

            return ['status' => 'unreachable', 'waiting' => 0, 'queues' => []];
        }

        $queues = array_values(array_map(
            fn (array $queue): array => ['name' => $queue['name'], 'length' => $queue['length'], 'wait' => $queue['wait']],
            $workload,
        ));

        return [
            'status' => $this->status($masters),
            'waiting' => array_sum(array_column($queues, 'length')),
            'queues' => $queues,
        ];
    }

    /**
     * @param  array<mixed>  $masters
     * @return 'running'|'paused'|'inactive'
     */
    private function status(array $masters): string
    {
        if ($masters === []) {
            return 'inactive';
        }

        return array_all($masters, fn (mixed $master): bool => is_object($master) && ($master->status ?? null) === 'paused') ? 'paused' : 'running';
    }
}
