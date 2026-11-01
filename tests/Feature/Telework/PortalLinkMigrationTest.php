<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('reverses and reapplies the derived portal link migration', function (): void {
    $migration = require database_path('migrations/2026_11_20_100000_derive_the_portal_link.php');

    $migration->down();

    expect(Schema::hasColumns('portal_links', ['previous_token_hash', 'previous_valid_until']))->toBeTrue()
        ->and(Schema::hasColumn('portal_links', 'version'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumns('portal_links', ['previous_token_hash']))->toBeFalse()
        ->and(Schema::hasColumn('portal_links', 'previous_valid_until'))->toBeFalse()
        ->and(Schema::hasColumn('portal_links', 'version'))->toBeTrue();
});
