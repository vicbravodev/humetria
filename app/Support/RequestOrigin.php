<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Geolocation;
use Carbon\CarbonInterface;

/**
 * Where a request came from, as a person recognizes it: "Chrome en macOS · Monterrey, Nuevo León · 30 oct, 14:02". So an
 * email about their account says whether it was them. The address itself is never kept.
 */
final readonly class RequestOrigin
{
    /** Checked in order: Edge and Opera also say "Chrome", and Chrome also says "Safari". */
    private const array BROWSERS = ['Edg/' => 'Edge', 'OPR/' => 'Opera', 'Firefox/' => 'Firefox', 'FxiOS/' => 'Firefox', 'CriOS/' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'];

    /** Checked in order: an iPhone also says "Mac OS X", and Android also says "Linux". */
    private const array SYSTEMS = ['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'CrOS' => 'ChromeOS', 'Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'];

    public function __construct(private Geolocation $geolocation) {}

    public function describe(string $userAgent, string $ip, CarbonInterface $at): string
    {
        $browser = self::first(self::BROWSERS, $userAgent);
        $system = self::first(self::SYSTEMS, $userAgent);
        $device = $browser !== null && $system !== null ? "{$browser} en {$system}" : ($browser ?? $system ?? 'Navegador desconocido');
        $when = str_replace('.', '', $at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM, HH:mm'));

        return implode(' · ', array_filter([$device, $this->geolocation->place($ip), $when]));
    }

    /**
     * @param  array<string, string>  $names
     */
    private static function first(array $names, string $userAgent): ?string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
