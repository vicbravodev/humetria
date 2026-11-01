<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Models\Consent;
use App\Modules\Telework\Models\TeleworkSettings;
use App\Modules\Telework\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sets how often Section II is applied and, when the text differs from the current one, adds a new consent version
 * (the old ones stay: each acceptance points at the text it accepted). A null text leaves the consent as it is.
 */
final readonly class UpdateTeleworkSettings
{
    public function __construct(private Settings $settings, private RecordAudit $audit) {}

    public function handle(int $periodMonths, ?string $consentText, int $userId): void
    {
        DB::transaction(function () use ($periodMonths, $consentText, $userId): void {
            // The row lock makes two edits at once take consecutive versions.
            $settings = TeleworkSettings::query()->lockForUpdate()->findOrFail($this->settings->record()->id);
            $current = $this->settings->currentConsent();
            $previous = $settings->review_period_months;
            $newText = $consentText === null ? null : self::normalize($consentText);
            $consent = $newText !== null && $newText !== self::normalize($current->text)
                ? Consent::query()->create(['tenant_id' => $settings->tenant_id, 'version' => $current->version + 1, 'text' => $newText, 'created_by' => $userId])
                : $current;

            if ($periodMonths === $previous && $consent->is($current)) {
                return;
            }

            $settings->update(['review_period_months' => $periodMonths]);
            // Values only, never the consent text.
            $change = ['period_months' => $periodMonths, 'previous_period_months' => $previous, 'consent_version' => $consent->version];
            $this->audit->handle('telework.settings_changed', $settings, $change);
            Log::info('telework.settings_changed', $change);
        });
    }

    /** A browser sends line breaks as CRLF: the same text must not become a new version for that. */
    private static function normalize(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }
}
