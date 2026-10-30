<?php

declare(strict_types=1);

namespace App\Modules\Responses\Providers;

use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Contracts\ResponseRecorder;
use App\Modules\Responses\Support\DatabaseResponseReader;
use App\Modules\Responses\Support\DatabaseResponseRecorder;
use Illuminate\Support\ServiceProvider;

final class ResponsesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ResponseRecorder::class, DatabaseResponseRecorder::class);
        $this->app->bind(ResponseReader::class, DatabaseResponseReader::class);
    }
}
