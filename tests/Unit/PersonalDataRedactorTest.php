<?php

declare(strict_types=1);

use App\Modules\Assistant\Support\PersonalDataRedactor;

mutates(PersonalDataRedactor::class);

it('masks each kind of personal data with its marker', function (string $text, string $expected): void {
    expect((new PersonalDataRedactor)->redact($text))->toBe($expected);
})->with([
    'an email' => ['Escríbele a ana.perez+rh@acme.com.mx hoy', 'Escríbele a [correo] hoy'],
    'a CURP' => ['Su CURP es PEPJ800101HDFRRN09.', 'Su CURP es [CURP].'],
    'a CURP in lowercase' => ['pepj800101hdfrrn09', '[CURP]'],
    'an RFC of a person' => ['RFC PEPJ800101AB3 del empleado', 'RFC [RFC] del empleado'],
    'an RFC of a company' => ['la razón social ACM800101AB3', 'la razón social [RFC]'],
    'an NSS' => ['NSS 12345678901 y 12-34-56-7890-1', 'NSS [NSS] y [NSS]'],
    'a phone with spaces' => ['llámale al 442 123 4567', 'llámale al [teléfono]'],
    'a phone with country code' => ['+52 55 1234 5678', '[teléfono]'],
    'a phone with parentheses' => ['(55) 1234-5678', '[teléfono]'],
    'a phone without separators' => ['4421234567', '[teléfono]'],
    'several in one message' => ['Juan, juan@x.mx, 4421234567, PEPJ800101HDFRRN09', 'Juan, [correo], [teléfono], [CURP]'],
]);

it('leaves the numbers of a compliance question alone', function (string $text): void {
    expect((new PersonalDataRedactor)->redact($text))->toBe($text);
})->with([
    'a count' => ['¿Cómo van 142 de 186 personas?'],
    'a norm' => ['Explícame la NOM-035 y la NOM-037-STPS-2023'],
    'a date' => ['La campaña cierra el 14 nov 2026 en Planta Apodaca'],
    'a percentage' => ['el 76 % de participación y el 40 % de la jornada'],
    'a criterion' => ['el criterio C-23 pide mínimo 5 personas'],
    'an id' => ['la campaña a321b7aa-deff-4010-ba96-d3d8a40a116d'],
    'plain text' => ['¿Qué me falta para estar al día?'],
]);

it('masks the same data every time it appears', function (): void {
    expect((new PersonalDataRedactor)->redact('a@b.mx y a@b.mx'))->toBe('[correo] y [correo]');
});
