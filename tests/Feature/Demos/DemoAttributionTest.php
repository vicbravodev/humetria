<?php

declare(strict_types=1);

use App\Modules\Demos\Actions\SubmitDemoRequest;
use App\Modules\Demos\Data\DemoAttribution;
use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Data\DemoTouch;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;

mutates(SubmitDemoRequest::class, DemoAttribution::class, DemoTouch::class);

function demoData(?DemoAttribution $attribution = null): DemoRequestData
{
    return new DemoRequestData(
        name: 'Ana López',
        email: 'ana@empresa.mx',
        whatsapp: '+525512345678',
        company: 'Empresa Ejemplo',
        profile: DemoProfile::Company,
        workforce: Workforce::From51To100,
        tools: [Tool::Nom035],
        message: null,
        attribution: $attribution,
    );
}

it('stores a request that arrives without attribution with every attribution column null', function (): void {
    $demoRequest = app(SubmitDemoRequest::class)->handle(demoData())->fresh();

    expect($demoRequest->arrivedBy())->toBeNull()
        ->and($demoRequest->firstContact())->toBeNull()
        ->and($demoRequest->utm_source)->toBeNull()
        ->and($demoRequest->first_referrer_host)->toBeNull();
});

it('credits the conversion to the last known channel and keeps the first one apart', function (): void {
    $attribution = new DemoAttribution(
        first: new DemoTouch(source: 'boletin', medium: 'correo', campaign: 'nom-037'),
        last: new DemoTouch(source: 'anuncios', medium: 'pago', campaign: 'nom-035', referrerHost: 'www.google.com'),
    );

    $demoRequest = app(SubmitDemoRequest::class)->handle(demoData($attribution))->fresh();

    expect($demoRequest->utm_source)->toBe('anuncios')
        ->and($demoRequest->utm_medium)->toBe('pago')
        ->and($demoRequest->utm_campaign)->toBe('nom-035')
        ->and($demoRequest->referrer_host)->toBe('www.google.com')
        ->and($demoRequest->first_utm_source)->toBe('boletin')
        ->and($demoRequest->first_utm_medium)->toBe('correo')
        ->and($demoRequest->first_utm_campaign)->toBe('nom-037')
        ->and($demoRequest->first_referrer_host)->toBeNull();
});

it('credits the conversion to the first channel when there is no later one', function (): void {
    $attribution = new DemoAttribution(first: new DemoTouch(referrerHost: 'www.bing.com'), last: new DemoTouch);

    $demoRequest = app(SubmitDemoRequest::class)->handle(demoData($attribution))->fresh();

    expect($demoRequest->referrer_host)->toBe('www.bing.com')
        ->and($demoRequest->first_referrer_host)->toBe('www.bing.com')
        ->and($demoRequest->arrivedBy())->toBe('www.bing.com')
        ->and($demoRequest->firstContact())->toBeNull();
});

it('tells an empty touch from one with any signal', function (DemoTouch $touch, bool $empty): void {
    expect($touch->isEmpty())->toBe($empty);
})->with([
    'nothing' => [new DemoTouch, true],
    'source' => [new DemoTouch(source: 'a'), false],
    'medium' => [new DemoTouch(medium: 'a'), false],
    'campaign' => [new DemoTouch(campaign: 'a'), false],
    'referrer host' => [new DemoTouch(referrerHost: 'a.mx'), false],
]);

it('stores nothing of where the request came from when both touches are empty', function (): void {
    $demoRequest = app(SubmitDemoRequest::class)->handle(demoData(new DemoAttribution(new DemoTouch, new DemoTouch)))->fresh();

    expect(DemoRequest::query()->whereNotNull('utm_source')->orWhereNotNull('referrer_host')->orWhereNotNull('first_utm_source')->count())->toBe(0)
        ->and($demoRequest->arrivedBy())->toBeNull();
});
