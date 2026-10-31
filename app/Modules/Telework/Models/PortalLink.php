<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\PortalLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The personal portal link of an employee, one per employee. The token is not random: it is derived with the app key from
 * the company, the employee and `version` (`tokenFor`), so every email carries the same link and nothing secret is
 * stored: only its hash, to find the row. RH revokes the link by moving the version.
 *
 * The app key and the keys in `APP_PREVIOUS_KEYS` derive valid links. Rotating `APP_KEY` without keeping the old one in
 * `APP_PREVIOUS_KEYS` revokes every portal link.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $token_hash
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'token_hash', 'version'])]
#[UseFactory(PortalLinkFactory::class)]
final class PortalLink extends Model
{
    /** @use HasFactory<PortalLinkFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The token of a link: HMAC-SHA256 of the company, the employee and the version, in base64url (43 characters). */
    public static function tokenFor(string $tenantId, string $employeeId, int $version, string $key): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', "portal|{$tenantId}|{$employeeId}|{$version}", $key, true)), '+/', '-_'), '=');
    }

    /**
     * The keys that derive a valid link: the app key first, then the previous ones.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [config()->string('app.key')];
    }

    /** The token this row was issued with, if a key still kept derives it; null once its key was dropped. */
    public function token(): ?string
    {
        foreach (self::keys() as $key) {
            $token = self::tokenFor($this->tenant_id, $this->employee_id, $this->version, $key);

            if (hash_equals($this->token_hash, self::hashToken($token))) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
