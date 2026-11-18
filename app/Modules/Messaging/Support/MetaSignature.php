<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * The signature Meta puts on every webhook: `X-Hub-Signature-256: sha256=<hex>`, the HMAC-SHA256 of the RAW body with the secret
 * of the app. Compared in constant time against every configured secret (the previous one too, while a rotation lasts).
 */
final class MetaSignature
{
    public function valid(string $rawBody, ?string $header): bool
    {
        if ($header === null || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $given = substr($header, strlen('sha256='));

        foreach (config()->array('whatsapp.app_secrets') as $secret) {
            if (is_string($secret) && $secret !== '' && hash_equals(hash_hmac('sha256', $rawBody, $secret), $given)) {
                return true;
            }
        }

        return false;
    }
}
