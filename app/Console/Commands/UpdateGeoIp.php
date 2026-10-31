<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads DB-IP's free city database (CC BY 4.0), published at the start of each month; while this month's is not out,
 * last month's. The file it replaces stays until the new one is whole, so lookups never read half a file.
 */
#[Signature('geoip:update')]
#[Description('Descarga la base de ciudades de DB-IP para ubicar las solicitudes')]
final class UpdateGeoIp extends Command
{
    public function handle(): int
    {
        foreach ([now(), now()->subMonthNoOverflow()] as $month) {
            $response = Http::timeout(120)->get('https://download.db-ip.com/free/dbip-city-lite-'.$month->format('Y-m').'.mmdb.gz');
            $database = $response->successful() ? @gzdecode($response->body()) : false;

            if ($database !== false) {
                $this->replace($database);
                Log::info('geoip.updated', ['month' => $month->format('Y-m')]);

                return self::SUCCESS;
            }
        }

        Log::warning('geoip.update_failed');

        return self::FAILURE;
    }

    private function replace(string $database): void
    {
        $path = config()->string('services.geoip.database');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        file_put_contents("{$path}.tmp", $database);
        rename("{$path}.tmp", $path);
    }
}
