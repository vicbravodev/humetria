<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
 * tests/bootstrap.php picks the database (see scripts/test-database.php). Pest's parallel workers load .env first,
 * which once sent them to the development database (`laravel_test_N`) shared by every checkout.
 */

it('never runs on the development database, in parallel or not', function (): void {
    expect(DB::connection()->getDatabaseName())->toStartWith('testing');
});
