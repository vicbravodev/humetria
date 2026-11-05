<?php

declare(strict_types=1);

namespace App\Modules\Results\Actions;

use App\Actions\RecordAudit;
use App\Modules\Results\Data\ReferralRecord;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Jobs\SendSupportNotice;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records, replacing what was there, the channeling of a person whose Guía I requires a clinical assessment (num. 5.5,
 * 5.8 c; board V4-N035-Canalizacion): where to, how they were told, the day, a note and, when asked, the day 30 days later
 * to ask how they are (a new record starts that reminder over). Told through their portal, the person gets an email that
 * says only that a message waits for them. The audit trail says who changed which status; the log only that a record was
 * saved: neither carries the person, the destination, the day or the note, which are health data.
 */
final readonly class RecordScreeningReferral
{
    public const int REMIND_AFTER_DAYS = 30;

    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $campaignId, string $employeeId, ReferralRecord $record, int $userId): ScreeningReferral
    {
        return DB::transaction(function () use ($campaignId, $employeeId, $record, $userId): ScreeningReferral {
            $referral = ScreeningReferral::query()->updateOrCreate(
                ['campaign_id' => $campaignId, 'employee_id' => $employeeId],
                [
                    'tenant_id' => $this->tenant->id(),
                    'status' => $record->status,
                    'destination' => $record->destination,
                    'told' => $record->told,
                    'referred_on' => $record->referredOn,
                    'note' => $record->note,
                    'remind_on' => $record->remind ? CarbonImmutable::parse($record->referredOn)->addDays(self::REMIND_AFTER_DAYS)->toDateString() : null,
                    'reminded_at' => null,
                    'updated_by' => $userId,
                ],
            );

            $this->audit->handle('results.referral_recorded', $referral, ['campaign_id' => $campaignId, 'status' => $record->status->value]);
            Log::info('screening_referral.saved', ['campaign_id' => $campaignId, 'referral_id' => $referral->id]);

            if ($record->told === HowTold::Portal) {
                SendSupportNotice::dispatch($this->tenant->id(), $referral->id);
            }

            return $referral;
        });
    }
}
