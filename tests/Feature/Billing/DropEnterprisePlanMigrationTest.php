<?php

declare(strict_types=1);

use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Facades\DB;

it('moves the subscriptions still on Enterprise to Integral before the database stops taking the plan', function (): void {
    $migration = require database_path('migrations/2027_02_03_100000_drop_enterprise_plan.php');
    $subscription = Subscription::factory()->create();
    $migration->down();
    DB::table('subscriptions')->where('id', $subscription->id)->update(['plan' => 'enterprise']);

    $migration->up();

    expect(DB::table('subscriptions')->where('id', $subscription->id)->value('plan'))->toBe('integral');
});
