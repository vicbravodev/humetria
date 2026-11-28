<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * The server posts to the address a phone says is its push service. Only the services of the browsers are allowed (https, no user,
 * no port, the host or a subdomain of it), or anybody with a session would make the server post wherever they write.
 */
final class PushHosts
{
    /** Chrome and Android; Firefox; Safari and the home screen app; Edge. */
    private const array HOSTS = ['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com']; // @pest-mutate-ignore: RemoveArrayItem

    public static function allows(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
