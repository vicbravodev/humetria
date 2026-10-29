<?php

declare(strict_types=1);

use App\Modules\Demos\Actions\SubmitDemoRequest;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Http\Requests\StoreDemoRequestRequest;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;

mutates(SubmitDemoRequest::class, StoreDemoRequestRequest::class);

beforeEach(function (): void {
    $this->logPath = storage_path('logs/demo-request-'.Str::uuid().'.log');
    config(['logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function demoRequest(array $overrides = []): array
{
    return [
        'name' => 'Ana López',
        'email' => 'ana@empresa.mx',
        'whatsapp' => '55 1234 5678',
        'company' => 'Empresa Ejemplo',
        'profile' => 'company',
        'workforce' => '51_100',
        'tools' => ['nom_035', 'ley_silla'],
        'message' => 'Queremos aplicar la Guía III este año.',
        'form_extra' => '',
        ...$overrides,
    ];
}

/** @return list<array<string, mixed>> */
function demoLogLines(string $path): array
{
    if (! File::exists($path)) {
        return [];
    }

    return array_values(array_map(
        fn (string $line): array => (array) json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        array_filter(explode("\n", File::get($path))),
    ));
}

it('stores a demo request and confirms it on the demo section', function (): void {
    $this->post(route('demo-requests.store'), demoRequest())
        ->assertRedirect(route('home').'#agenda-una-demo')
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => [
            'type' => 'success',
            'message' => 'Recibimos tu solicitud · te escribiremos para agendar la demo.',
        ]]);

    $demoRequest = DemoRequest::query()->sole();

    expect($demoRequest->name)->toBe('Ana López')
        ->and($demoRequest->email)->toBe('ana@empresa.mx')
        ->and($demoRequest->whatsapp)->toBe('+525512345678')
        ->and($demoRequest->company)->toBe('Empresa Ejemplo')
        ->and($demoRequest->profile->value)->toBe('company')
        ->and($demoRequest->workforce->value)->toBe('51_100')
        ->and($demoRequest->tools->all())->toBe([Tool::Nom035, Tool::LeySilla])
        ->and($demoRequest->message)->toBe('Queremos aplicar la Guía III este año.')
        ->and(Str::isUuid($demoRequest->id, 4))->toBeTrue()
        ->and($demoRequest->created_at)->not->toBeNull();
});

it('accepts a demo request without a message', function (?string $message): void {
    $this->post(route('demo-requests.store'), demoRequest(['message' => $message]))
        ->assertSessionHasNoErrors();

    expect(DemoRequest::query()->sole()->message)->toBeNull();
})->with(['missing' => null, 'empty' => '']);

it('normalizes a Mexican WhatsApp number to E.164', function (string $whatsapp): void {
    $this->post(route('demo-requests.store'), demoRequest(['whatsapp' => $whatsapp]))
        ->assertSessionHasNoErrors();

    expect(DemoRequest::query()->sole()->whatsapp)->toBe('+525512345678');
})->with([
    '10 digits' => '5512345678',
    'spaces' => '55 1234 5678',
    'dashes' => '55-1234-5678',
    'parentheses and dots' => '(55) 1234.5678',
    '12 digits with 52' => '525512345678',
    '+52 and spaces' => '+52 55 1234 5678',
    '+52 and dashes' => '+52-55-1234-5678',
]);

it('rejects a WhatsApp number that is not a Mexican phone', function (string $whatsapp): void {
    $this->post(route('demo-requests.store'), demoRequest(['whatsapp' => $whatsapp]))
        ->assertSessionHasErrors(['whatsapp' => 'Escribe un WhatsApp de 10 dígitos, por ejemplo 55 1234 5678.']);

    expect(DemoRequest::query()->count())->toBe(0);
})->with([
    '9 digits' => '551234567',
    '11 digits' => '15512345678',
    '12 digits without 52' => '155512345678',
    '13 digits' => '5215512345678',
    'plus without 52' => '+5512345678',
    'letters' => '55 1234 567a',
    'plus in the middle' => '55+12345678',
]);

it('requires every field but the message', function (string $field, string $error): void {
    $this->post(route('demo-requests.store'), demoRequest([$field => '']))
        ->assertSessionHasErrors([$field => $error]);

    expect(DemoRequest::query()->count())->toBe(0);
})->with([
    ['name', 'El campo nombre es obligatorio.'],
    ['email', 'El campo correo electrónico es obligatorio.'],
    ['whatsapp', 'El campo WhatsApp es obligatorio.'],
    ['company', 'El campo empresa es obligatorio.'],
    ['profile', 'El campo perfil es obligatorio.'],
    ['workforce', 'El campo número de trabajadores es obligatorio.'],
]);

it('accepts each field at its maximum length', function (): void {
    $this->post(route('demo-requests.store'), demoRequest([
        'name' => str_repeat('a', 120),
        'email' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 58).'.mx',
        'company' => str_repeat('a', 160),
        'message' => str_repeat('a', 2000),
    ]))->assertSessionHasNoErrors();

    expect(DemoRequest::query()->count())->toBe(1);
});

it('rejects a field that is too long', function (string $field, string $value, string $error): void {
    $this->post(route('demo-requests.store'), demoRequest([$field => $value]))
        ->assertSessionHasErrors([$field => $error]);

    expect(DemoRequest::query()->count())->toBe(0);
})->with([
    ['name', str_repeat('a', 121), 'El campo nombre no debe tener más de 120 caracteres.'],
    ['email', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 59).'.mx', 'El campo correo electrónico no debe tener más de 190 caracteres.'],
    ['company', str_repeat('a', 161), 'El campo empresa no debe tener más de 160 caracteres.'],
    ['message', str_repeat('a', 2001), 'El campo mensaje no debe tener más de 2000 caracteres.'],
]);

it('rejects an email that is not valid', function (string $email): void {
    $this->post(route('demo-requests.store'), demoRequest(['email' => $email]))
        ->assertSessionHasErrors(['email' => 'El campo correo electrónico debe ser un correo electrónico válido.']);

    expect(DemoRequest::query()->count())->toBe(0);
})->with(['no at' => 'ana.empresa.mx', 'no domain' => 'ana@', 'two ats' => 'ana@@empresa.mx']);

it('rejects invalid UTF-8 and control characters before they reach the database', function (string $field, string $value): void {
    $this->post(route('demo-requests.store'), demoRequest(['name' => 'Ana Lopez Prueba', 'company' => 'Empresa Prueba Uno', $field => $value]))
        ->assertSessionHasErrors([$field]);

    expect(DemoRequest::query()->count())->toBe(0);

    // Exception handling stays on: nothing may be reported with the submitted values in it.
    $log = File::exists($this->logPath) ? File::get($this->logPath) : '';

    foreach (['Ana Lopez Prueba', 'Empresa Prueba Uno', 'ana@empresa.mx', '5512345678'] as $personal) {
        expect($log)->not->toContain($personal);
    }
})->with([
    'name, invalid UTF-8' => ['name', "Ana\xff"],
    'name, NUL' => ['name', "Ana\0x"],
    'name, control' => ['name', "Ana\x1bx"],
    'name, new line' => ['name', "Ana\nx"],
    'company, invalid UTF-8' => ['company', "Empresa\xff"],
    'company, NUL' => ['company', "Empresa\0x"],
    'email, invalid UTF-8' => ['email', "ana\xff@empresa.mx"],
    'email, NUL' => ['email', "ana\0@empresa.mx"],
    'message, invalid UTF-8' => ['message', "Hola\xff"],
    'message, NUL' => ['message', "Hola\0x"],
    'message, DEL' => ['message', "Hola\x7f"],
    'whatsapp, invalid UTF-8' => ['whatsapp', "55\xff12345678"],
    'whatsapp, NUL' => ['whatsapp', "55\x0012345678"],
]);

it('keeps line breaks and tabs in the message', function (): void {
    $this->post(route('demo-requests.store'), demoRequest(['message' => "Hola,\r\n\tqueremos una demo."]))
        ->assertSessionHasNoErrors();

    expect(DemoRequest::query()->sole()->message)->toBe("Hola,\r\n\tqueremos una demo.");
});

it('ignores a website field, so browser autofill never drops a real request', function (): void {
    $this->post(route('demo-requests.store'), demoRequest(['website' => 'https://empresa.mx']))
        ->assertSessionHasNoErrors();

    expect(DemoRequest::query()->count())->toBe(1);
});

it('rejects a value outside the options', function (string $field, mixed $value, string $error): void {
    $this->post(route('demo-requests.store'), demoRequest([$field => $value]))
        ->assertSessionHasErrors([$field => $error]);

    expect(DemoRequest::query()->count())->toBe(0);
})->with([
    ['profile', 'employee', 'El perfil seleccionado no es válido.'],
    ['workforce', '1_50', 'El número de trabajadores seleccionado no es válido.'],
    ['workforce', ['51_100'], 'El número de trabajadores seleccionado no es válido.'],
]);

it('rejects tools that are missing, empty, unknown, repeated or not a list', function (mixed $tools, string $key, ?string $error): void {
    $this->post(route('demo-requests.store'), demoRequest(['tools' => $tools]))
        ->assertSessionHasErrors($error === null ? [$key] : [$key => $error]);

    expect(DemoRequest::query()->count())->toBe(0);
})->with([
    'missing' => [null, 'tools', 'El campo herramientas es obligatorio.'],
    'empty' => [[], 'tools', 'El campo herramientas es obligatorio.'],
    'string' => ['nom_035', 'tools', 'El campo herramientas debe ser un arreglo.'],
    'unknown' => [['nom_036'], 'tools.0', null],
    'repeated' => [['nom_035', 'nom_035'], 'tools.1', null],
]);

it('rejects text fields sent as arrays', function (string $field): void {
    $this->post(route('demo-requests.store'), demoRequest([$field => ['x']]))
        ->assertSessionHasErrors([$field]);
})->with(['name', 'email', 'whatsapp', 'company', 'message']);

it('stores each profile and workforce range', function (string $profile, string $workforce): void {
    $this->post(route('demo-requests.store'), demoRequest(['profile' => $profile, 'workforce' => $workforce]))
        ->assertSessionHasNoErrors();

    $demoRequest = DemoRequest::query()->sole();

    expect($demoRequest->profile->value)->toBe($profile)
        ->and($demoRequest->workforce->value)->toBe($workforce);
})->with([
    ['company', '1_15'],
    ['consultant', '16_50'],
    ['company', '101_500'],
    ['consultant', '501_plus'],
]);

it('logs the submission without any personal data', function (): void {
    $this->post(route('demo-requests.store'), demoRequest([
        'name' => 'Ana Lopez Prueba',
        'whatsapp' => '+52 55 1234 5678',
        'message' => 'Queremos aplicar la guia tres',
    ]));

    $demoRequest = DemoRequest::query()->sole();
    $lines = collect(demoLogLines($this->logPath))->where('message', 'demo_request.submitted');

    expect($lines)->toHaveCount(1)
        ->and($lines->first()['context'])->toBe([
            'demo_request_id' => $demoRequest->id,
            'profile' => 'company',
            'workforce' => '51_100',
            'tools' => ['nom_035', 'ley_silla'],
        ]);

    $log = File::get($this->logPath);

    // ASCII values: the JSON log escapes accented letters, so they would never match.
    foreach (['Ana Lopez Prueba', 'ana@empresa.mx', '5512345678', 'Empresa Ejemplo', 'guia tres'] as $personal) {
        expect($log)->not->toContain($personal);
    }
});

it('answers a filled honeypot like a success but stores nothing', function (): void {
    $this->post(route('demo-requests.store'), demoRequest(['name' => 'Ana Lopez Prueba', 'form_extra' => 'https://spam.example']))
        ->assertRedirect(route('home').'#agenda-una-demo')
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => [
            'type' => 'success',
            'message' => 'Recibimos tu solicitud · te escribiremos para agendar la demo.',
        ]]);

    expect(DemoRequest::query()->count())->toBe(0);

    $lines = demoLogLines($this->logPath);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['message'])->toBe('demo_request.rejected')
        ->and($lines[0]['context'])->toBe(['reason' => 'honeypot'])
        ->and(File::get($this->logPath))->not->toContain('Ana Lopez Prueba')
        ->not->toContain('ana@empresa.mx')
        ->not->toContain('spam.example');
});

it('does not log a submission it could not store', function (): void {
    DB::statement('ALTER TABLE demo_requests ADD CONSTRAINT reject_all CHECK (false) NOT VALID');

    // The failed insert aborts the test's wrapping transaction, so only the response and the log can be checked.
    $this->post(route('demo-requests.store'), demoRequest())->assertServerError();

    expect(collect(demoLogLines($this->logPath))->where('message', 'demo_request.submitted'))->toHaveCount(0);
});

it('throttles one address to 5 requests per minute', function (): void {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('demo-requests.store'), demoRequest())->assertRedirect();
    }

    $this->post(route('demo-requests.store'), demoRequest())
        ->assertTooManyRequests()
        ->assertSee('Recibimos demasiadas solicitudes desde tu conexión. Espera un momento e inténtalo de nuevo.');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('demo-requests.store'), demoRequest())
        ->assertRedirect();

    expect(DemoRequest::query()->count())->toBe(6);
});

it('throttles one address to 20 requests per day', function (): void {
    foreach (range(1, 4) as $minute) {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('demo-requests.store'), demoRequest())->assertRedirect();
        }

        $this->travel(2)->minutes();
    }

    $this->post(route('demo-requests.store'), demoRequest())->assertTooManyRequests();

    $this->travel(1)->days();

    $this->post(route('demo-requests.store'), demoRequest())->assertRedirect();

    expect(DemoRequest::query()->count())->toBe(21);
});
