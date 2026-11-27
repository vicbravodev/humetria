<?php

declare(strict_types=1);

use App\Modules\Messaging\Console\ExportTemplates;
use App\Modules\Messaging\Support\TemplateDefinition;
use App\Modules\Messaging\Support\TemplateExport;
use App\Modules\Messaging\Support\TemplateRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

mutates(ExportTemplates::class, TemplateExport::class);

/** @return array<string, mixed> */
function exported(): array
{
    Artisan::call('messaging:export-templates');
    $json = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    return is_array($json) ? $json : [];
}

beforeEach(function (): void {
    URL::forceRootUrl('https://humetria.test');
    URL::forceHttps();
    Http::preventStrayRequests();
    Http::fake();
    config(['whatsapp.access_token' => 'the-secret-token', 'whatsapp.phone_number_id' => 'the-phone-number-id']);
});

it('prints the creation request of every template as Meta asks for it, and calls nobody', function (): void {
    $all = exported();

    expect(array_keys($all))->toEqualCanonicalizing(array_keys((new TemplateRegistry)->all()));
    Http::assertNothingSent();
});

it('writes the named variables, their examples and the buttons of an invitation', function (): void {
    $template = exported()['survey_invite_v1'];

    expect($template['name'])->toBe('survey_invite_v1')
        ->and($template['category'])->toBe('UTILITY')
        ->and($template['language'])->toBe('es_MX')
        ->and($template['parameter_format'])->toBe('named')
        ->and($template['components'])->toBe([
            ['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Su evaluación de la NOM-035'],
            ['type' => 'BODY', 'text' => 'Hola, buen día. Le escribimos de parte de {{empresa}}. Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.', 'example' => ['body_text_named_params' => [
                ['param_name' => 'empresa', 'example' => 'Industrias Ejemplo'],
                ['param_name' => 'fecha_limite', 'example' => 'viernes 20 de noviembre'],
            ]]],
            ['type' => 'FOOTER', 'text' => 'Humetria'],
            ['type' => 'BUTTONS', 'buttons' => [
                ['type' => 'URL', 'text' => 'Contestar ahora', 'url' => str_replace('PLACEHOLDER', '{{1}}', route('survey.show', 'PLACEHOLDER')), 'example' => [route('survey.show', 'abc123')]],
                ['type' => 'URL', 'text' => 'Aviso de privacidad', 'url' => route('privacy-notice')],
                ['type' => 'QUICK_REPLY', 'text' => 'Dejar de recibir'],
            ]],
        ]);
});

it('exports the climate invitation header as fixed text, with no example', function (): void {
    $header = exported()['engagement_invite_v1']['components'][0];

    expect($header)->toBe(['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Encuesta anónima de clima']);
});

it('gives the header its own example when it has a variable', function (): void {
    $template = new TemplateDefinition(
        key: 'sample_v1',
        language: 'es_MX',
        body: 'Hola. Le escribimos de parte de {{empresa}}. Tiene hasta el {{fecha_limite}} para contestar.',
        examples: ['empresa' => 'Industrias Ejemplo', 'fecha_limite' => 'viernes 20 de noviembre'],
        header: 'Encuesta de {{empresa}}',
        footer: null,
        buttons: [],
    );

    expect((new TemplateExport)->build($template)['components'][0])->toBe(['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Encuesta de {{empresa}}', 'example' => ['header_text_named_params' => [['param_name' => 'empresa', 'example' => 'Industrias Ejemplo']]]]);
});

it('marks a button without a destination as pending, with its reason, and does not invent the destination', function (): void {
    $buttons = exported()['leave_decision_v1']['components'][2]['buttons'];

    expect($buttons[0])->toHaveKey('_pending')->not->toHaveKey('url')
        ->and($buttons[1])->toBe(['type' => 'QUICK_REPLY', 'text' => 'Dejar de recibir']);
});

it('exports the proof code as a template of the authentication library, with no text of ours', function (): void {
    expect(exported()['proof_code_v1'])->toBe([
        'name' => 'proof_code_v1',
        'language' => 'es_MX',
        'category' => 'AUTHENTICATION',
        'components' => [
            ['type' => 'BODY', 'add_security_recommendation' => true],
            ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copiar código']]],
        ],
    ]);
});

it('writes one JSON file per template into a directory, and still sends nothing', function (): void {
    $directory = sys_get_temp_dir().'/templates-'.bin2hex(random_bytes(4));

    try {
        Artisan::call('messaging:export-templates', ['--path' => $directory]);

        $files = collect(File::files($directory))->map(fn ($file): string => $file->getFilename())->sort()->values()->all();

        expect($files)->toBe(collect(array_keys((new TemplateRegistry)->all()))->map(fn (string $key): string => "{$key}.json")->sort()->values()->all())
            ->and(json_decode((string) file_get_contents("{$directory}/survey_reminder_v1.json"), true))->toBe(exported()['survey_reminder_v1'])
            ->and(Artisan::output())->not->toContain('the-secret-token');
        Http::assertNothingSent();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('never reads the credentials', function (): void {
    expect(json_encode(exported()))->not->toContain('the-secret-token')->not->toContain('the-phone-number-id');
});

it('exports every URL button with https and the variable at the end', function (): void {
    foreach (exported() as $template) {
        foreach ($template['components'] as $component) {
            foreach ($component['buttons'] ?? [] as $button) {
                if (isset($button['url'])) {
                    expect($button['url'])->toStartWith('https://');
                    expect(str_ends_with($button['url'], '{{1}}'))->toBe(isset($button['example']));
                    expect(substr_count($button['url'], '{{'))->toBe(isset($button['example']) ? 1 : 0);
                }
            }
        }
    }
});

it('exports no phone button', function (): void {
    expect(json_encode(exported()))->not->toContain('PHONE_NUMBER')->not->toContain('Llamar a RH');
});

it('fails, writing nothing, when a URL is not https: Meta would reject it', function (): void {
    URL::forceRootUrl('http://humetria.test');
    URL::forceScheme('http');
    $directory = sys_get_temp_dir().'/templates-'.bin2hex(random_bytes(4));

    try {
        $exit = Artisan::call('messaging:export-templates', ['--path' => $directory]);

        expect($exit)->toBe(1)->and(Artisan::output())->toContain('https')->and(is_dir($directory))->toBeFalse();
    } finally {
        File::deleteDirectory($directory);
    }
});
