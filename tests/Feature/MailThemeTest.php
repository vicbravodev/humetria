<?php

declare(strict_types=1);

use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;

function genericMail(): MailMessage
{
    return (new MailMessage)->subject('Crea una contraseña nueva')->greeting('Hola, Ana')->line('Texto')->action('Aceptar', 'https://humetria.test/a');
}

it('dresses every email in the Humetría design system', function (): void {
    expect((string) genericMail()->render())
        ->toContain('src="'.asset('brand/humetria-horizontal-color.png').'"')
        ->toContain('alt="Humetría"')
        ->toContain('https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600')
        // The frame around the card, and the card itself: white, with the border token and the large radius.
        ->toContain('background-color: #f2f6f5')
        ->toContain('border: 1px solid #dce5e2')
        ->toContain('border-radius: 14px')
        // The action: primary, 52 px tall, on the left with the text.
        ->toContain('background-color: #0b7a6f')
        ->toContain('class="action" align="left"')
        // Secondary text in muted-foreground, which passes 4.5:1 on the frame and the card.
        ->toContain('color: #56635f')
        ->toContain('Si el botón no funciona, copia este enlace en tu navegador:')
        ->not->toContain('Saludos,')
        ->not->toContain('Todos los derechos reservados.')
        ->not->toContain('#18181b')
        ->not->toContain('#a1a1aa');
});

it('uses only CSS the mail clients understand', function (): void {
    expect((string) genericMail()->render())
        // Outlook, Yahoo, AOL and Gmail with other accounts ignore `start`: the text would fall back to the client's default.
        ->toContain('text-align: left')
        ->not->toContain('text-align: start')
        ->not->toContain('position:')
        ->not->toContain('vw;')
        ->not->toContain('float:')
        ->not->toContain('overflow:')
        ->not->toContain('display: flex')
        ->not->toContain('display: grid')
        ->not->toContain('var(--')
        ->not->toContain('<svg');
});

it('titles the email with its headline and keeps the greeting as a line of the text', function (): void {
    expect((string) genericMail()->render())
        ->toMatch('#<h1[^>]*>Crea una contraseña nueva</h1>#')
        ->toMatch('#<p[^>]*>Hola, Ana:</p>#');
});

it('shows the preview text in the inbox and nowhere in the email', function (): void {
    $html = (string) genericMail()->markdown('notifications::email', ['preheader' => 'El enlace vence en 60 minutos.'])->render();

    expect($html)->toMatch('#<div class="preheader"[^>]*display: none[^>]*>El enlace vence en 60 minutos\.#');
});

it('signs the footer with the legal address and the privacy notice when they are configured', function (): void {
    config(['mail.legal.address' => 'Av. Constitución 100, Monterrey, N. L.', 'mail.legal.privacy_url' => 'https://humetria.mx/privacidad']);

    expect((string) genericMail()->render())
        ->toContain('Humetría · Av. Constitución 100, Monterrey, N. L. · <a href="https://humetria.mx/privacidad"')
        ->toContain('Aviso de privacidad');
});

it('leaves out of the footer what is not configured', function (): void {
    config(['mail.legal.address' => null, 'mail.legal.privacy_url' => null]);

    expect((string) genericMail()->render())
        ->toMatch('#<p class="legal"[^>]*>Humetría</p>#')
        ->not->toContain('Aviso de privacidad');
});

it('ships the PNGs the emails load, since mail clients do not show SVG', function (string $file): void {
    expect(public_path($file))->toBeFile();
})->with(['brand/humetria-horizontal-color.png', 'mail/lock.png', 'mail/info.png', 'mail/monitor.png', 'mail/hand-heart.png', 'mail/file-text.png', 'mail/arc/0.png', 'mail/arc/100.png', 'mail/arc/closed-0.png', 'mail/arc/closed-100.png']);

it('writes the plain-text email as text, with the link spelled out', function (): void {
    $mail = genericMail();
    $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());

    expect($text)->toContain('Crea una contraseña nueva')
        ->toContain('Hola, Ana:')
        ->toContain('Aceptar: https://humetria.test/a')
        ->not->toContain('<');
});
