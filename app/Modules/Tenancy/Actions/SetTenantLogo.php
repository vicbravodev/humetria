<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gives a company the logo its people see on their emails. Whatever came in (PNG, JPG, WebP) is kept as a PNG 80 px tall
 * and at most 400 px wide, with its transparency, so every mail client shows it sharp at half that size; nothing of the
 * original file (its metadata, its name) is kept.
 */
final readonly class SetTenantLogo
{
    private const int HEIGHT = 80;

    private const int MAX_WIDTH = 400;

    public function handle(Tenant $tenant, string $imagePath): Tenant
    {
        $source = @imagecreatefromstring((string) file_get_contents($imagePath));

        if ($source === false) {
            throw new InvalidArgumentException('The logo is not an image.');
        }

        $scale = min(self::HEIGHT / imagesy($source), self::MAX_WIDTH / imagesx($source));
        $width = max(2, (int) round(imagesx($source) * $scale));
        $height = max(2, (int) round(imagesy($source) * $scale));
        $logo = imagecreatetruecolor($width, $height);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, (int) imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagecopyresampled($logo, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagepng($logo);
        $png = (string) ob_get_clean();

        $disk = Storage::disk(config()->string('filesystems.tenant_logos'));
        $path = "logos/{$tenant->id}/".Str::uuid().'.png';
        $disk->put($path, $png, 'public');
        $previous = $tenant->logo_path;

        $tenant->update(['logo_path' => $path, 'logo_width' => intdiv($width, 2), 'logo_height' => intdiv($height, 2)]);

        if ($previous !== null) {
            $disk->delete($previous);
        }

        Log::info('tenant.logo_updated', ['tenant_id' => $tenant->id]);

        return $tenant;
    }
}
