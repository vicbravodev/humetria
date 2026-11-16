<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
 * The registro de jornada no longer links phones: a person creates their key with the link RH issues from «Accesos»
 * (WorkerAccess). Nothing of the old enrollment is left to reach.
 */
it('has no route, table or setting of the old enrollment of a phone', function (): void {
    foreach (['attendance.device.enroll.show', 'attendance.device.enroll.options', 'attendance.device.enroll.register', 'portal.attendance.enroll', 'tenant.attendance.devices.index', 'tenant.attendance.devices.enroll', 'tenant.attendance.devices.enroll-all', 'tenant.attendance.devices.revoke'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }

    expect(Schema::hasTable('attendance_enrollment_links'))->toBeFalse()
        ->and(config('attendance.enrollment'))->toBeNull();
});
