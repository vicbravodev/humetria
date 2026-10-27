<?php

declare(strict_types=1);

it('names the app Humetría', function (): void {
    $this->get(route('login'))->assertSee('<title>Humetría</title>', false);
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
