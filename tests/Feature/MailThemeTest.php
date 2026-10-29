<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;

it('dresses every email in the Humetría design system', function (MailMessage $mail): void {
    expect((string) $mail->render())
        ->toContain('src="'.asset('brand/humetria-horizontal-color.png').'"')
        ->toContain('alt="Humetría"')
        ->toContain('Figtree')
        ->toContain('https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600')
        // One reading line: the action sits on the left with the text, not centered like Laravel's.
        ->toContain('class="action" align="left"')
        // The action is the one accent, primary, with white text on it.
        ->toContain('background-color: #0b7a6f')
        // Secondary text in muted-foreground, which passes 4.5:1 on the page and the card.
        ->toContain('color: #56635f')
        ->toContain('Enviado por Humetría, herramientas para Recursos Humanos.')
        ->not->toContain('NOM-035')
        ->toContain('¿El botón no abre? Copia este enlace en tu navegador:')
        // No filler: no salutation and no copyright line.
        ->not->toContain('Saludos,')
        ->not->toContain('Todos los derechos reservados.')
        // Laravel's own ink and its footer gray, which does not reach 4.5:1.
        ->not->toContain('#18181b')
        ->not->toContain('#a1a1aa');
})->with([
    'a notification' => fn (): MailMessage => (new MailMessage)->greeting('Hola')->action('Aceptar', 'https://humetria.test'),
    'the Fortify password reset' => fn (): MailMessage => (new ResetPassword('token'))->toMail(User::factory()->make()),
]);

it('ships the PNG of the logo the emails load, since mail clients do not show SVG', function (): void {
    expect(public_path('brand/humetria-horizontal-color.png'))->toBeFile();
});

it('writes the Fortify emails in Spanish', function (MailMessage $mail, string $subject, string $action): void {
    expect($mail->subject)->toBe($subject)
        ->and($mail->actionText)->toBe($action)
        ->and((string) $mail->render())
        ->not->toContain('You are receiving')
        ->not->toContain('no further action')
        ->not->toContain('Please click');
})->with([
    'password reset' => [
        fn (): MailMessage => (new ResetPassword('token'))->toMail(User::factory()->make()),
        'Restablece tu contraseña de Humetría',
        'Restablecer contraseña',
    ],
    'email verification' => [
        fn (): MailMessage => (new VerifyEmail)->toMail(User::factory()->create()),
        'Verifica tu correo en Humetría',
        'Verificar correo',
    ],
]);

it('titles every email with its subject and keeps the greeting as the line above it', function (): void {
    $html = (string) (new MailMessage)->subject('Restablece tu contraseña')->greeting('Hola, Ana')->render();

    expect($html)->toMatch('#<h1[^>]*>Restablece tu contraseña</h1>#')
        ->toMatch('#<p class="greeting"[^>]*>Hola, Ana</p>#');
});

it('signs the plain-text emails as Humetría, HR tools', function (): void {
    $mail = (new MailMessage)->greeting('Hola')->action('Aceptar', 'https://humetria.test');

    expect((string) app(Markdown::class)->renderText($mail->markdown, $mail->data()))
        ->toContain('Enviado por Humetría, herramientas para Recursos Humanos.')
        ->not->toContain('NOM-035');
});
