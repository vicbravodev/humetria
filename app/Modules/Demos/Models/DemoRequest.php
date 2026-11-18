<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use App\Modules\Demos\Enums\AdClickType;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use Database\Factories\Demos\DemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A request for a demo from the landing. Platform data: it belongs to no tenant.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $whatsapp
 * @property string $company
 * @property ?DemoProfile $profile
 * @property ?Workforce $workforce
 * @property Collection<int, Tool> $tools
 * @property string|null $message
 * @property DemoStatus $status
 * @property string|null $note
 * @property Carbon|null $handled_at
 * @property string|null $tenant_id
 * @property string|null $utm_source The campaign this request is credited to: the last known channel, else the first.
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer_host Only a host, and only an external one: never a URL.
 * @property string|null $first_utm_source The first known channel.
 * @property string|null $first_utm_medium
 * @property string|null $first_utm_campaign
 * @property string|null $first_referrer_host
 * @property AdClickType|null $ad_click_type The last ad click, for the import into Google Ads; forgotten after 90 days.
 * @property string|null $ad_click_id
 * @property string|null $utm_term The keyword the ad was shown for, from the final URL suffix.
 * @property string|null $utm_content
 * @property string|null $match_type
 * @property Carbon $created_at
 */
#[Fillable(['name', 'email', 'whatsapp', 'company', 'profile', 'workforce', 'tools', 'message', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'first_utm_source', 'first_utm_medium', 'first_utm_campaign', 'first_referrer_host', 'ad_click_type', 'ad_click_id', 'utm_term', 'utm_content', 'match_type'])]
#[UseFactory(DemoRequestFactory::class)]
final class DemoRequest extends Model
{
    /** @use HasFactory<DemoRequestFactory> */
    use HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile' => DemoProfile::class,
            'workforce' => Workforce::class,
            'tools' => AsEnumCollection::of(Tool::class),
            'status' => DemoStatus::class,
            'ad_click_type' => AdClickType::class,
            'handled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The channel this request is credited to, as the console reads it. Null is "no data", which is NOT direct traffic:
     * many browsers and messaging apps send no referrer at all.
     */
    public function arrivedBy(): ?string
    {
        return self::channel($this->utm_source, $this->utm_medium, $this->utm_campaign, $this->referrer_host);
    }

    /** The first known channel, only when it is not the one the request arrived by. */
    public function firstContact(): ?string
    {
        $first = self::channel($this->first_utm_source, $this->first_utm_medium, $this->first_utm_campaign, $this->first_referrer_host);

        return $first === $this->arrivedBy() ? null : $first;
    }

    /** Source and medium joined by " / ", the campaign after " · "; with no utm at all, the referrer host. */
    private static function channel(?string $source, ?string $medium, ?string $campaign, ?string $host): ?string
    {
        $origin = implode(' / ', array_filter([$source, $medium], fn (?string $part): bool => $part !== null));

        if ($origin === '' && $campaign === null) {
            return $host;
        }

        return implode(' · ', array_filter([$origin, $campaign], fn (?string $part): bool => $part !== null && $part !== ''));
    }
}
