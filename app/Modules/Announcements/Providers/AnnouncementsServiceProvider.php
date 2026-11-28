<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Providers;

use App\Models\User;
use App\Modules\Announcements\Contracts\PlatformNotices;
use App\Modules\Announcements\Support\LiveNotices;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Announcements: what Humetría tells the companies in their panel, and the platform's maintenance. */
final class AnnouncementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PlatformNotices::class, LiveNotices::class);
    }

    public function boot(): void
    {
        // The superadmin's alone, through Gate::before; everyone else, support included, gets a 404 that reveals nothing.
        Gate::define('manage-announcements', fn (User $user): AccessResponse => AccessResponse::denyAsNotFound());
    }
}
