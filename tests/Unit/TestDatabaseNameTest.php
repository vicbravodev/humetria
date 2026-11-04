<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/scripts/test-database.php';

/*
 * Parallel worktrees share one Postgres, so each gets its own test database: the main checkout keeps `testing`, a
 * worktree gets `testing_<slug>_<hash>`. Parallel workers append `_test_N` and Postgres cuts identifiers at 63.
 */

it('keeps `testing` in the main checkout', function (): void {
    expect(testDatabaseName('/code/humetria', false))->toBe('testing');
});

it('gives each worktree its own database, named after its directory', function (): void {
    $name = testDatabaseName('/code/humetria/.claude/worktrees/feature-x', true);

    expect($name)->toStartWith('testing_feature_x_')
        ->and($name)->not->toBe(testDatabaseName('/code/humetria/.claude/worktrees/feature-y', true));
});

it('is stable for the same path', function (): void {
    expect(testDatabaseName('/a/b/wt', true))->toBe(testDatabaseName('/a/b/wt', true));
});

it('tells apart two worktrees whose directory names are the same', function (): void {
    expect(testDatabaseName('/one/wt', true))->not->toBe(testDatabaseName('/two/wt', true));
});

it('keeps the name valid and short enough for the parallel suffix, whatever the directory is called', function (): void {
    $name = testDatabaseName('/x/'.str_repeat('Very Long-Name!', 20), true);

    expect(strlen($name))->toBeLessThanOrEqual(63 - strlen('_test_12'))
        ->and($name)->toMatch('/^[a-z0-9_]+$/');
});
