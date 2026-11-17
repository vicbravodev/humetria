<?php

declare(strict_types=1);

use App\Support\PhoneNumber;

mutates(PhoneNumber::class);

it('stores a number as E.164, Mexico when no country is written', function (string $typed, string $stored): void {
    expect(PhoneNumber::e164($typed))->toBe($stored);
})->with([
    'ten digits' => ['5512345678', '+525512345678'],
    'ten digits with spaces' => ['55 1234 5678', '+525512345678'],
    'with parentheses and dashes' => ['(55) 1234-5678', '+525512345678'],
    'with +52' => ['+52 55 1234 5678', '+525512345678'],
    'with 52 and no plus' => ['525512345678', '+525512345678'],
    'with the old 1 of mobiles' => ['+52 1 55 1234 5678', '+525512345678'],
    'with the old 1, no plus' => ['5215512345678', '+525512345678'],
    'with the old 1 alone' => ['1 55 1234 5678', '+525512345678'],
    'with the old 044' => ['044 55 1234 5678', '+525512345678'],
    'with the old 045' => ['045 55 1234 5678', '+525512345678'],
    'a landline of Querétaro' => ['442 123 4567', '+524421234567'],
    'a landline of Monterrey' => ['81 1234 5678', '+528112345678'],
    'already E.164' => ['+525512345678', '+525512345678'],
    'the United States' => ['+1 415 555 2671', '+14155552671'],
    'a plus 1 is the United States, not the old 1' => ['+1 551 234 5678', '+15512345678'],
    'a Spanish mobile' => ['+34 612 345 678', '+34612345678'],
    'dialed from Mexico abroad' => ['00 34 612 345 678', '+34612345678'],
]);

it('gives nothing for what is no phone number of its country', function (string $typed): void {
    expect(PhoneNumber::e164($typed))->toBeNull();
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'too short' => ['55 1234'],
    'eleven digits' => ['55123456789'],
    'not a Mexican area code' => ['1234567890'],
    'a country code and little else' => ['+52 1234'],
    'letters' => ['cinco cinco'],
    'an impossible Spanish number' => ['+34 12'],
]);

it('lets a valid number through and rejects the rest with its message', function (mixed $value, bool $passes): void {
    $validator = Validator::make(['mobile' => $value], ['mobile' => [new PhoneNumber]]);

    expect($validator->passes())->toBe($passes);

    if (! $passes) {
        expect($validator->errors()->first('mobile'))->toBe('Revisa el celular: no es un número válido para el país elegido.');
    }
})->with([
    'E.164' => ['+525512345678', true],
    'as typed' => ['55 1234 5678', true],
    'abroad' => ['+14155552671', true],
    'impossible' => ['+52 1234', false],
    'not text' => [5512345678, false],
]);
