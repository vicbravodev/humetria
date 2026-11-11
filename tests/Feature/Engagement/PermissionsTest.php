<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('gives each role the climate permissions of the spec', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-climate'))->toBe($view)
        ->and(Gate::allows('manage-climate'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
    'complaints' => [TenantRole::Complaints, false, false],
]);

it('tells the sidebar who may open the climate survey and who may change it', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.view_climate', $view)
            ->where('tenant.can.manage_climate', $manage));
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
]);

it('names the permission each kind of survey needs', function (): void {
    expect(SurveyKind::Climate->value)->toBe('climate')
        ->and(SurveyKind::Climate->viewPermission())->toBe('view-climate')
        ->and(SurveyKind::Climate->managePermission())->toBe('manage-climate')
        ->and(SurveyKind::Pulse->value)->toBe('pulse')
        ->and(SurveyKind::Pulse->viewPermission())->toBe('view-pulse')
        ->and(SurveyKind::Pulse->managePermission())->toBe('manage-pulse');
});

it('keeps the comment minimum and the trend minimum from being lowered by the environment', function (): void {
    expect(config('engagement.min_comments'))->toBe(10)
        ->and(config('engagement.min_trend'))->toBe(30)
        ->and(config('engagement.comment_max_length'))->toBe(1000)
        ->and(config('engagement.min_theme_support'))->toBe(3);
});

it('ignores an environment value under the floor of the comment and trend minimums', function (): void {
    $config = require base_path('config/engagement.php');
    putenv('ENGAGEMENT_MIN_COMMENTS=3');
    putenv('ENGAGEMENT_MIN_TREND=5');
    $_ENV['ENGAGEMENT_MIN_COMMENTS'] = '3';
    $_ENV['ENGAGEMENT_MIN_TREND'] = '5';

    try {
        $lowered = require base_path('config/engagement.php');
    } finally {
        putenv('ENGAGEMENT_MIN_COMMENTS');
        putenv('ENGAGEMENT_MIN_TREND');
        unset($_ENV['ENGAGEMENT_MIN_COMMENTS'], $_ENV['ENGAGEMENT_MIN_TREND']);
    }

    expect($config['min_comments'])->toBe(10)
        ->and($lowered['min_comments'])->toBe(10)
        ->and($lowered['min_trend'])->toBe(30);
});
