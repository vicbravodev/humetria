<?php

declare(strict_types=1);

use App\Contracts\Geolocation;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

mutates(ResetPasswordNotification::class, VerifyEmailNotification::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-30 20:02 UTC');
    config(['mail.legal.address' => null]);
});

it('asks for a new password, saying from where and when it was requested', function (): void {
    Notification::fake();
    app()->instance(Geolocation::class, new readonly class implements Geolocation
    {
        public function place(string $ip): ?string
        {
            return 'Monterrey, Nuevo León';
        }
    });
    $user = User::factory()->create(['email' => 'rita@autopartes.mx']);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36')
        ->post(route('password.email'), ['email' => 'rita@autopartes.mx']);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $html = (string) $mail->render();

        expect($mail->subject)->toBe('Crea una contraseña nueva')
            ->and($mail->actionText)->toBe('Crear contraseña nueva')
            ->and($mail->actionUrl)->toContain('/reset-password/'.$notification->token)
            ->and($html)
            ->toContain('El enlace vence en 60 minutos.')
            ->toMatch('#<h1[^>]*>Crea una contraseña nueva</h1>#')
            ->toContain('Recibimos una solicitud para cambiar la contraseña de rita@autopartes.mx.')
            ->toContain('El enlace vence en 60 minutos. Si no fuiste tú, ignora este correo: tu contraseña no cambia.')
            ->toContain('Solicitud desde Chrome en macOS · Monterrey, Nuevo León · 30 oct, 14:02')
            // DB-IP's license (CC BY 4.0) asks for the credit where its data shows.
            ->toContain('IP Geolocation by DB-IP')
            ->toContain('Te escribimos porque alguien pidió cambiar tu contraseña en Humetría.')
            ->toContain('alt="Humetría"');

        return true;
    });
});

it('keeps the address the request came from out of the queued email', function (): void {
    $notification = new ResetPasswordNotification('tok', 'Chrome en macOS · 30 oct, 14:02');

    expect(serialize($notification))->not->toContain('127.0.0.1');
});

it('asks to verify an email, in Spanish', function (): void {
    $user = User::factory()->unverified()->create(['email' => 'rita@autopartes.mx']);

    $mail = (new VerifyEmailNotification)->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Verifica tu correo en Humetría')
        ->and($mail->actionText)->toBe('Verificar correo')
        ->and($mail->actionUrl)->toContain('/email/verify/')
        ->and($html)
        ->toContain('El enlace vence en 60 minutos.')
        ->toMatch('#<h1[^>]*>Verifica tu correo</h1>#')
        ->toContain('Confirma que rita@autopartes.mx es tu correo para entrar a Humetría.')
        ->toContain('Te escribimos porque se creó una cuenta de Humetría con este correo.')
        ->not->toContain('Please click')
        ->not->toContain('no further action');
});
