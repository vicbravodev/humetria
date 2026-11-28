<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoLeads;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;

it('tells other modules how many requests are new and how many of them wait more than 48 hours', function (): void {
    $this->travelTo('2026-12-01 12:00 UTC');
    DemoRequest::factory()->create(['created_at' => now()->subHours(47)->subMinutes(59)]);
    DemoRequest::factory()->create(['created_at' => now()->subHours(48)->subMinute()]);
    DemoRequest::factory()->create(['created_at' => now()->subDays(9)]);
    DemoRequest::factory()->create(['status' => DemoStatus::Contacted, 'created_at' => now()->subDays(9)]);
    DemoRequest::factory()->create(['status' => DemoStatus::Discarded, 'created_at' => now()->subDays(9)]);

    $waiting = app(DemoLeads::class)->waiting();

    expect($waiting->new)->toBe(3)->and($waiting->unattended)->toBe(2);
});

it('counts nothing waiting when nobody asked', function (): void {
    $waiting = app(DemoLeads::class)->waiting();

    expect($waiting->new)->toBe(0)->and($waiting->unattended)->toBe(0);
});

it('reads what waits in one query', function (): void {
    DemoRequest::factory()->count(3)->create();

    $this->expectsDatabaseQueryCount(1);
    app(DemoLeads::class)->waiting();
});
