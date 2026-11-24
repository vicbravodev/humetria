<?php

declare(strict_types=1);

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\SendOutcome;

mutates(FailureCause::class, SendOutcome::class);

it('has the sixteen causes of the design', function (): void {
    expect(array_map(fn (FailureCause $cause): string => $cause->value, FailureCause::cases()))->toBe([
        'invalid_token', 'template_not_approved', 'no_whatsapp_account', 'outside_window', 'rate_limited', 'server_error',
        'timed_out', 'retries_exhausted', 'spam_limited', 'ecosystem_blocked', 'opted_out', 'invitation_closed', 'paused',
        'disabled', 'unresolved', 'unknown_error',
    ]);
});

it('retries only a rate limit and a server error', function (): void {
    $retryable = array_filter(FailureCause::cases(), fn (FailureCause $cause): bool => $cause->retryable());

    expect(array_values(array_map(fn (FailureCause $cause): string => $cause->value, $retryable)))->toBe(['rate_limited', 'server_error']);
});

it('falls back to mail only after the definitive failures of rule 9', function (): void {
    $falls = array_filter(FailureCause::cases(), fn (FailureCause $cause): bool => $cause->fallsBackToMail());

    expect(array_values(array_map(fn (FailureCause $cause): string => $cause->value, $falls)))
        ->toBe(['invalid_token', 'template_not_approved', 'no_whatsapp_account', 'retries_exhausted', 'opted_out']);
});

it('is accepted with the id of the provider, or failed with a cause', function (): void {
    $accepted = SendOutcome::accepted('wamid.1');
    $failed = SendOutcome::failed(FailureCause::NoWhatsappAccount);

    expect($accepted->isAccepted())->toBeTrue()
        ->and($accepted->providerMessageId)->toBe('wamid.1')
        ->and($accepted->cause)->toBeNull()
        ->and($failed->isAccepted())->toBeFalse()
        ->and($failed->providerMessageId)->toBeNull()
        ->and($failed->cause)->toBe(FailureCause::NoWhatsappAccount);
});
