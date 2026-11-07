<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\MaskedEmail;

mutates(MaskedEmail::class);

it('keeps the first letter of the name and of the domain, and the ending, to tell a changed address apart', function (string $email, string $masked): void {
    expect(MaskedEmail::of($email))->toBe($masked);
})->with([
    'plain' => ['juan@empresa.com', 'j***@e***.com'],
    'subdomain and country' => ['ana.lopez@correo.acme.com.mx', 'a***@c***.mx'],
    'one letter each' => ['a@b.io', 'a***@b***.io'],
    'capitals and spaces' => ['  Pepe@Acme.Test ', 'p***@a***.test'],
    'accented' => ['ñandú@águila.mx', 'ñ***@á***.mx'],
]);

it('never gives back an address that is not one', function (string $email): void {
    expect(MaskedEmail::of($email))->toBe('***');
})->with(['no at' => ['juan.empresa.com'], 'no domain' => ['juan@'], 'no name' => ['@empresa.com'], 'no ending' => ['juan@empresa'], 'empty' => ['']]);
