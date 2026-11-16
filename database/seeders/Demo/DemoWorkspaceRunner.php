<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Tenancy\Contracts\Companies;
use Database\Seeders\Demo\Stories\OrganizationStory;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Tells the demo's story, one module at a time and in this order: each story stands on what the ones before it built.
 */
final readonly class DemoWorkspaceRunner implements DemoWorkspace
{
    public function __construct(private OrganizationStory $organization, private Companies $companies) {}

    public function prepare(): void
    {
        $tenant = $this->organization->company();
        // From here on every action works inside the demo company: its data, logs and audit entries.
        $this->companies->enter($tenant->id);
        $context = $this->organization->members($tenant);

        foreach ($this->stories() as $story) {
            $story->tell($context);
        }

        Log::info('demo.prepared', ['tenant_id' => $tenant->id]);
    }

    public function reset(): void
    {
        // ponytail: wiping the demo company and telling the story again comes with its lock and progress (plan, Task 13).
        throw new LogicException('Resetting the demo is not built yet.');
    }

    /** @return list<DemoStory> */
    private function stories(): array
    {
        return [$this->organization];
    }
}
