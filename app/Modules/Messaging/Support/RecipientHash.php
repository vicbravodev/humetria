<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use LogicException;

/**
 * The HMAC-SHA256 of a phone in E.164, the only form in which a phone is stored here. Keys are versioned (rule 6): new rows use
 * the newest; a STOP looks for the hash under every key of the list, because a hash cannot be recomputed without the phone.
 */
final class RecipientHash
{
    /** @return array{hash: string, version: int} */
    public function current(string $e164): array
    {
        $keys = $this->keys();
        $version = max(array_keys($keys));

        return ['hash' => hash_hmac('sha256', $e164, $keys[$version]), 'version' => $version];
    }

    /** @return list<string> */
    public function all(string $e164): array
    {
        return array_values(array_map(fn (string $key): string => hash_hmac('sha256', $e164, $key), $this->keys()));
    }

    /** @return non-empty-array<int, string> */
    private function keys(): array
    {
        $keys = [];

        foreach (config()->array('messaging.recipient_keys') as $version => $key) {
            $decoded = is_string($key) ? base64_decode($key, true) : false;

            if (! is_int($version) || $decoded === false || $decoded === '') {
                throw new LogicException('MESSAGING_RECIPIENT_KEYS must be version:base64 pairs.');
            }

            $keys[$version] = $decoded;
        }

        return $keys === [] ? throw new LogicException('MESSAGING_RECIPIENT_KEYS is empty: a phone cannot be hashed.') : $keys;
    }
}
