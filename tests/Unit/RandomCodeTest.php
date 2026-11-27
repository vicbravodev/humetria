<?php

declare(strict_types=1);

use App\Modules\Complaints\Support\RandomCode;
use App\Modules\Complaints\Support\RandomReferenceSource;

mutates(RandomCode::class, RandomReferenceSource::class);

it('draws codes of the length asked from the unambiguous Crockford alphabet', function (int $length): void {
    expect(RandomCode::make($length))->toMatch('/^[0-9A-HJKMNP-TV-Z]{'.$length.'}$/');
})->with([8, 16, 20]);

it('never repeats itself in practice', function (): void {
    $codes = array_map(fn (): string => RandomCode::make(16), range(1, 200));

    expect(array_unique($codes))->toHaveCount(200);
});

it('uses every symbol of the alphabet', function (): void {
    expect(count_chars(implode('', array_map(fn (): string => RandomCode::make(20), range(1, 400))), 3))->toBe('0123456789ABCDEFGHJKMNPQRSTVWXYZ');
});

it('draws a complaint reference of 8 symbols', function (): void {
    expect((new RandomReferenceSource)->next())->toMatch('/^[0-9A-HJKMNP-TV-Z]{8}$/');
});
