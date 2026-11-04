<?php

declare(strict_types=1);

use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Models\Tenant;

/*
 * Company A reads nothing of company B's Programa, by query. (By URL, 404, each page of the module has its own test.)
 */
it('does not read the Programa of another company with a query', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $action = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    $area = Area::factory()->create(['tenant_id' => $other->id]);
    ActionArea::factory()->create(['tenant_id' => $other->id, 'action_id' => $action->id, 'area_id' => $area->id]);
    ProgressEntry::factory()->create(['tenant_id' => $other->id, 'action_id' => $action->id]);
    $ids = [Program::query()->sole()->id, ProgramItem::query()->sole()->id, $action->id];

    workIn(Tenant::factory()->create());

    expect(Program::query()->count())->toBe(0)
        ->and(ProgramItem::query()->count())->toBe(0)
        ->and(ProgramAction::query()->count())->toBe(0)
        ->and(ActionArea::query()->count())->toBe(0)
        ->and(ProgressEntry::query()->count())->toBe(0)
        ->and(Program::query()->find($ids[0]))->toBeNull()
        ->and(ProgramAction::query()->find($ids[2]))->toBeNull();

    workIn($other);
    expect(Program::query()->count())->toBe(1)->and(ProgressEntry::query()->count())->toBe(1);
});
