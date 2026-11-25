<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use Illuminate\Support\Facades\Log;

/**
 * The VAPID keys and subject the push is signed with (`config('services.vapid')`). A wrong or missing setting never stops the
 * app: it only turns the push off, saying why (`push.unconfigured`), so the app does not offer the notices either.
 */
final readonly class VapidConfig
{
    private function __construct(private string $publicKey, private string $privateKey, private string $subject) {}

    /** The settings, or null (logged) when a key is missing or the subject is neither `mailto:` nor `https://` (as written, in lower case). */
    public static function fromConfig(): ?self
    {
        $public = config('services.vapid.public_key');
        $private = config('services.vapid.private_key');
        $subject = config('services.vapid.subject');

        if (! is_string($public) || $public === '' || ! is_string($private) || $private === '' || ! is_string($subject)) {
            Log::warning('push.unconfigured', ['reason' => 'missing_keys']);

            return null;
        }

        if (! str_starts_with($subject, 'mailto:') && ! str_starts_with($subject, 'https://')) {
            Log::warning('push.unconfigured', ['reason' => 'subject_invalid']);

            return null;
        }

        return new self($public, $private, $subject);
    }

    /** What the phone subscribes with. */
    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @return array{VAPID: array{subject: string, publicKey: string, privateKey: string}}
     */
    public function auth(): array
    {
        return ['VAPID' => ['subject' => $this->subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]];
    }
}
