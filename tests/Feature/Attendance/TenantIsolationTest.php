<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Tenancy\Models\Tenant;

/*
 * Company A reads none of company B's attendance data. By URL it is pinned when the panel pages exist.
 */
it('does not read the marks of another company with a query', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    $markOfB = AttendanceMark::factory()->create(['tenant_id' => $b->id]);

    workIn($a);
    expect(AttendanceMark::query()->count())->toBe(0)
        ->and(AttendanceMark::query()->find($markOfB->id))->toBeNull();

    workIn($b);
    expect(AttendanceMark::query()->count())->toBe(1);
});
