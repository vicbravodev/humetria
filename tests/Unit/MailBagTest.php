<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\MailBag;

/**
 * Every email a module sends is either counted against the company's campaign quota or is essential (access, recovery,
 * security, accidents) and free: the notification says which, so a new one cannot be left uncounted by accident.
 */
it('makes every notification of every module declare its mail bag', function (): void {
    $classes = [];
    foreach (glob(__DIR__.'/../../app/Modules/*/Notifications/*.php') ?: [] as $file) {
        $classes[] = 'App\\Modules\\'.basename(dirname($file, 2)).'\\Notifications\\'.basename($file, '.php');
    }

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(is_subclass_of($class, MailBag::class))->toBeTrue("{$class} must implement MailBag");
    }
});

it('puts the emails that carry access, recovery, security or an accident in the free bag', function (): void {
    $essential = ['InvitationEmail', 'PortalInvitation', 'EnrollmentInvitation', 'DeviceLinked', 'AccidentReported'];

    foreach (glob(__DIR__.'/../../app/Modules/*/Notifications/*.php') ?: [] as $file) {
        $name = basename($file, '.php');
        $source = (string) file_get_contents($file);
        $bag = in_array($name, $essential, true) ? 'ESSENTIAL' : 'CAMPAIGN';

        expect($source)->toContain("return MailBag::{$bag};");
    }
});
