<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('drops the portal links, and going back makes the table again as it stood, empty', function (): void {
    $migration = require database_path('migrations/2027_01_23_100000_drop_portal_links_table.php');

    expect(Schema::hasTable('portal_links'))->toBeFalse();

    $migration->down();

    expect(Schema::hasColumns('portal_links', ['id', 'tenant_id', 'employee_id', 'token_hash', 'version', 'created_at', 'updated_at']))->toBeTrue()
        ->and(Schema::hasColumns('portal_links', ['previous_token_hash']))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('portal_links'))->toBeFalse();
});
