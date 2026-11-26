<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\LogoBounds;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Gives a company the logo its people see on their emails. Whatever came in (PNG, JPG, WebP) is trimmed of its empty margins and kept as a PNG 80 px tall
 * and at most 400 px wide, with its transparency, so every mail client shows it sharp at half that size; nothing of the
 * original file (its metadata, its name) is kept.
 */
final readonly class SetTenantLogo
{
    private const int HEIGHT = 80;

    private const int MAX_WIDTH = 400;

    /** @param  bool  $dark  the version for dark mode, kept beside the one for light */
    public function handle(Tenant $tenant, string $imagePath, bool $dark = false): Tenant
    {
        $source = @imagecreatefromstring((string) file_get_contents($imagePath));

        if ($source === false) {
            throw new InvalidArgumentException('The logo is not an image.');
        }

        // Only the mark: the empty margins of the file would shrink it in the space it is shown in.
        $mark = LogoBounds::of($source);
        $scale = min(self::HEIGHT / $mark['height'], self::MAX_WIDTH / $mark['width']);
        $width = max(2, (int) round($mark['width'] * $scale));
        $height = max(2, (int) round($mark['height'] * $scale));
        $logo = imagecreatetruecolor($width, $height);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, (int) imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagecopyresampled($logo, $source, 0, 0, $mark['x'], $mark['y'], $width, $height, $mark['width'], $mark['height']);

        ob_start();
        imagepng($logo);
        $png = (string) ob_get_clean();

        $disk = Storage::disk(config()->string('filesystems.tenant_logos'));
        $column = $dark ? 'logo_dark' : 'logo';
        $path = "logos/{$tenant->id}/".Str::uuid().'.png';
        $disk->put($path, $png, 'public');
        $previous = $tenant->{$column.'_path'};

        $tenant->update(["{$column}_path" => $path, "{$column}_width" => intdiv($width, 2), "{$column}_height" => intdiv($height, 2)]);

        if ($previous !== null) {
            $disk->delete($previous);
        }

        Log::info($dark ? 'tenant.logo_dark_updated' : 'tenant.logo_updated', ['tenant_id' => $tenant->id]);

        return $tenant;
    }
}
