<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Disclosure\PageLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The permanent link to the page of the workers, one per company (criterio C-55). The token is not random: it is derived with
 * the app key from the company and `version` (`tokenFor`), so RH sees the same link every time and the poster keeps working;
 * only its hash is stored, to find the row. RH revokes the link by moving the version. The app key and the keys in
 * `APP_PREVIOUS_KEYS` derive valid links: rotating `APP_KEY` without keeping the old one in `APP_PREVIOUS_KEYS` kills every
 * printed poster.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $version
 * @property string $token_hash
 * @property CarbonImmutable $enabled_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $rotated_at
 * @property int $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Table('disclosure_page_links')]
#[Fillable(['tenant_id', 'version', 'token_hash', 'enabled_at', 'disabled_at', 'expires_at', 'rotated_at', 'created_by'])]
#[UseFactory(PageLinkFactory::class)]
final class PageLink extends Model
{
    /** @use HasFactory<PageLinkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The token of a link: HMAC-SHA256 of the company and the version, in base64url (43 characters). */
    public static function tokenFor(string $tenantId, int $version, string $key): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', "page|{$tenantId}|{$version}", $key, true)), '+/', '-_'), '=');
    }

    /**
     * The keys that derive a valid link: the app key first, then the previous ones.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_values(array_filter([config()->string('app.key'), ...config()->array('app.previous_keys')], is_string(...)));
    }

    /** The token this row was issued with, if a key still kept derives it; null once its key was dropped. */
    public function token(): ?string
    {
        foreach (self::keys() as $key) {
            $token = self::tokenFor($this->tenant_id, $this->version, $key);

            if (hash_equals($this->token_hash, self::hashToken($token))) {
                return $token;
            }
        }

        return null;
    }

    public function status(): PageLinkStatus
    {
        return match (true) {
            $this->disabled_at !== null => PageLinkStatus::Disabled,
            $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now()) => PageLinkStatus::Expired,
            default => PageLinkStatus::Active,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'enabled_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'rotated_at' => 'immutable_datetime',
        ];
    }
}
