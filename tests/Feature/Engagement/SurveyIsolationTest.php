<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Models\Tenant;

it('does not read or change the surveys of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $surveyOfB = Survey::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(Survey::query()->count())->toBe(0)
        ->and(Survey::query()->find($surveyOfB->id))->toBeNull()
        ->and(Survey::query()->whereKey($surveyOfB->id)->update(['name' => 'Tomada']))->toBe(0)
        ->and(Survey::query()->whereKey($surveyOfB->id)->delete())->toBe(0);

    workIn($b);
    expect(Survey::query()->sole()->name)->not->toBe('Tomada');
});
