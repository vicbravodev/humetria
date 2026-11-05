<?php

declare(strict_types=1);

use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\QrCodeImage;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(QrCodeImage::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme Industrial']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create(['procedure' => "1. Se recibe la queja.\n2. La atiende una persona designada."]);
});

/** The SVG a data URI carries. */
function svgOf(string $dataUri): string
{
    return (string) base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true);
}

it('draws the QR of an address as an SVG data URI that the policy of the pages allows', function (): void {
    $uri = resolve(QrCodeImage::class)->dataUri('https://humetria.test/c/ABCDEFGH23456789');

    expect($uri)->toStartWith('data:image/svg+xml;base64,')
        ->and(svgOf($uri))->toContain('<svg')->toContain('<path')
        ->and(resolve(QrCodeImage::class)->dataUri('https://humetria.test/c/ABCDEFGH23456789'))->toBe($uri)
        ->and(resolve(QrCodeImage::class)->dataUri('https://humetria.test/c/ZZZZZZZZ23456789'))->not->toBe($uri);
});

// Rediseño v2 (board V4-Carteles, decisión A16): the letter sheet promises the answer in the days the company set, instead of
// a paragraph of the procedure, which the person reads on the channel's page.
it('shows the poster of an active channel with its QR, its address and the days to the answer', function (): void {
    $this->channel->forceFill(['resolve_within_days' => 20])->save();

    $this->actingAs($this->owner)->get(route('tenant.complaints.poster', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('complaints/poster')
            ->where('company', 'Acme Industrial')
            ->where('url', $this->channel->publicUrl())
            ->where('shortUrl', preg_replace('#^https?://#', '', $this->channel->publicUrl()))
            ->where('qr', resolve(QrCodeImage::class)->dataUri($this->channel->publicUrl()))
            ->where('resolveWithinDays', 20)
            ->missing('procedure'));
});

it('gives the new address after the code is rotated, and the printed one no more', function (): void {
    $old = $this->channel->publicUrl();
    $this->actingAs($this->owner)->post(route('tenant.complaints.channel.rotate', $this->tenant));

    $response = $this->actingAs($this->owner)->get(route('tenant.complaints.poster', $this->tenant));

    expect($response->viewData('page')['props']['url'])->not->toBe($old)->and($response->viewData('page')['props']['url'])->toBe($this->channel->refresh()->publicUrl());
    $this->get(parse_url($old, PHP_URL_PATH))->assertNotFound();
});

it('has no poster for a channel that was never activated, nor for a company without one', function (): void {
    $this->channel->forceFill(['enabled_at' => null, 'published_on' => null])->save();

    $this->actingAs($this->owner)->get(route('tenant.complaints.poster', $this->tenant))->assertNotFound();

    ComplaintChannel::query()->delete();
    $this->actingAs($this->owner)->get(route('tenant.complaints.poster', $this->tenant))->assertNotFound();
});

it('still draws the poster of a paused channel, which says so', function (): void {
    $this->channel->forceFill(['paused_at' => now()])->save();

    $this->actingAs($this->owner)->get(route('tenant.complaints.poster', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page->where('paused', true));
});

it('keeps the poster to the people who manage the channel', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.complaints.poster', $this->tenant))->assertForbidden();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);
