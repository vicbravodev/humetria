<?php

declare(strict_types=1);

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Support\WhatsAppErrorMap;

mutates(WhatsAppErrorMap::class);

dataset('meta codes', [
    [190, FailureCause::InvalidToken],
    [132000, FailureCause::TemplateNotApproved],
    [132001, FailureCause::TemplateNotApproved],
    [132015, FailureCause::TemplateNotApproved],
    [132016, FailureCause::TemplateNotApproved],
    [131026, FailureCause::NoWhatsappAccount],
    [131047, FailureCause::OutsideWindow],
    [130429, FailureCause::RateLimited],
    [131056, FailureCause::RateLimited],
    [80007, FailureCause::RateLimited],
    [131048, FailureCause::SpamLimited],
    [131049, FailureCause::EcosystemBlocked],
    [131000, FailureCause::ServerError],
    [131016, FailureCause::ServerError],
    [999999, FailureCause::UnknownError],
]);

it('maps each Meta code', function (int $code, FailureCause $cause): void {
    expect((new WhatsAppErrorMap)->fromCode($code))->toBe($cause)
        ->and((new WhatsAppErrorMap)->fromResponse(400, $code))->toBe($cause);
})->with('meta codes');

it('maps the HTTP status when the code says nothing more specific', function (): void {
    $map = new WhatsAppErrorMap;

    expect($map->fromResponse(429, null))->toBe(FailureCause::RateLimited)
        ->and($map->fromResponse(500, null))->toBe(FailureCause::ServerError)
        ->and($map->fromResponse(503, null))->toBe(FailureCause::ServerError)
        ->and($map->fromResponse(400, null))->toBe(FailureCause::UnknownError)
        ->and($map->fromResponse(429, 131026))->toBe(FailureCause::RateLimited);
});
