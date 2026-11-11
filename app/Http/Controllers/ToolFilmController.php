<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a landing film from public/video. A static file is not enough: a browser can only jump inside a video (the
 * chapters of a tool's film) when the server answers byte ranges, and a file response does.
 */
final class ToolFilmController
{
    public function __invoke(string $film): BinaryFileResponse
    {
        abort_unless(preg_match('/^humetria-[a-z0-9-]+\.(mp4|webm)$/', $film) === 1, 404);

        $path = public_path('video/'.$film);

        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }
}
