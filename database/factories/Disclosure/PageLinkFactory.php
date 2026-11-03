<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Models\User;
use App\Modules\Disclosure\Models\PageLink;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The link to the page of the workers, version 1, active and without an expiry. Its hash is that of the derived token.
 *
 * @extends Factory<PageLink>
 */
final class PageLinkFactory extends Factory
{
    protected $model = PageLink::class;

    /**
     * @return array<model-property<PageLink>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'version' => 1,
            'token_hash' => fn (array $attributes): string => PageLink::hashToken(PageLink::tokenFor(is_string($attributes['tenant_id']) ? $attributes['tenant_id'] : '', 1, config()->string('app.key'))),
            'enabled_at' => now(),
            'disabled_at' => null,
            'expires_at' => null,
            'rotated_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
