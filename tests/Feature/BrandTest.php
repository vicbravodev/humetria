<?php

declare(strict_types=1);

it('names the app Humetría', function (): void {
    $this->get(route('login'))->assertSee('<title>Humetría</title>', false);
});

it('falls back to Humetría when APP_NAME is not set', function (): void {
    $original = getenv('APP_NAME');
    unset($_ENV['APP_NAME'], $_SERVER['APP_NAME']);
    putenv('APP_NAME');

    $name = (require config_path('app.php'))['name'];

    if ($original !== false) {
        $_ENV['APP_NAME'] = $_SERVER['APP_NAME'] = $original;
        putenv("APP_NAME={$original}");
    }

    expect($name)->toBe('Humetría');
});

it('links favicons that exist', function (string $href): void {
    $this->get(route('login'))->assertSee("href=\"{$href}\"", false);
    expect(public_path(ltrim($href, '/')))->toBeFile();
})->with(['/favicon.svg', '/favicon-32.png', '/apple-touch-icon-180.png']);

it('paints the first frame with the design system backgrounds', function (): void {
    $this->get(route('login'))->assertSee('#FBFDFC', false)->assertSee('#0F1716', false);
});

it('ships the official brand files the Logo component loads', function (string $file): void {
    expect(public_path("brand/{$file}"))->toBeFile();
})->with([
    'humetria-horizontal-color.svg', 'humetria-horizontal-color-oscuro.svg',
    'humetria-simbolo-color.svg', 'humetria-simbolo-color-oscuro.svg',
    'humetria-sello-hecho-con-gris.svg', 'humetria-sello-hecho-con-blanco.svg',
]);
