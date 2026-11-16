<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Exceptions\ChannelOverCap;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Notifications\DailyCapReached;
use App\Modules\Complaints\Support\ComplaintInput;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Complaints\Support\RandomCode;
use App\Modules\Complaints\Support\Receipt;
use App\Modules\Complaints\Support\ReferenceSource;
use App\Modules\Complaints\Support\ReporterNotifier;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Receives a complaint from the public form. Nothing identifies the person: no address, browser, cookie or hour is kept,
 * only the day. The person leaves with a reference and a key; the key is kept as a hash. The log line carries no id, so
 * that no line can be tied to a case by the moment it was written (criterio C-46). Past the channel's daily cap the complaint is
 * still received, but held: nobody is assigned or mailed until `ReleaseHeldComplaints` frees it on a later day.
 */
final readonly class SubmitComplaint
{
    private const int REFERENCE_ATTEMPTS = 5;

    public function __construct(
        private CurrentTenant $tenant,
        private ReferenceSource $references,
        private Members $members,
        private Companies $companies,
        private AssignComplaint $assign,
        private ReporterNotifier $notifier,
    ) {}

    /**
     * @throws ChannelOverCap when even the held complaints of the day reached their ceiling.
     */
    public function handle(ComplaintChannel $channel, ComplaintInput $input): Receipt
    {
        $today = Dates::today();
        $held = $this->overCap($channel, $today->toDateString());

        $key = RandomCode::make(20);
        $reference = $this->store($channel, $input, $key, $today->toDateString(), $held);

        // A held complaint is receipted like any other, but nobody is told of it until it is released (the next day).
        if ($held) {
            Log::info('complaint.held', ['channel_id' => $channel->id]);

            return new Receipt($reference, $key);
        }

        Log::info('complaints.submitted', ['channel_id' => $channel->id, 'type' => $input->type->value, 'identity_mode' => $input->identityMode->value]);
        $this->notifier->received($channel, $input->notifyEmail);

        return new Receipt($reference, $key);
    }

    private function store(ComplaintChannel $channel, ComplaintInput $input, string $key, string $day, bool $held): string
    {
        for ($attempt = 1; ; $attempt++) {
            $reference = $this->references->next();

            try {
                DB::transaction(function () use ($channel, $input, $key, $day, $reference, $held): void {
                    $complaint = Complaint::query()->create([
                        'tenant_id' => $this->tenant->id(),
                        'channel_id' => $channel->id,
                        'reference' => $reference,
                        'access_key_hash' => hash('sha256', $key),
                        'type' => $input->type,
                        'category' => $input->category,
                        'respondent_kind' => $input->respondentKind,
                        'work_center_id' => $input->workCenterId,
                        'identity_mode' => $input->identityMode,
                        'status' => ComplaintStatus::New,
                        'description' => $input->description,
                        'respondent_text' => $input->respondentText,
                        'reporter_name' => $input->reporterName,
                        'reporter_contact' => $input->reporterContact,
                        'reporter_email' => $input->notifyEmail,
                        'involved_text' => $input->involvedText,
                        'occurred_on' => $input->occurredOn,
                        'received_on' => $day,
                        'held' => $held,
                    ]);

                    if (! $held) {
                        $this->assign->handle($complaint);
                    }
                });

                return $reference;
            } catch (UniqueConstraintViolationException $collision) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $collision;
                }
            }
        }
    }

    /** Whether the channel is past its daily cap, in which case the complaint is held. */
    private function overCap(ComplaintChannel $channel, string $day): bool
    {
        $cap = config()->integer('complaints.rate_limits.daily_cap');

        $today = Complaint::query()->where('channel_id', $channel->id)->whereDate('received_on', $day);

        if ((clone $today)->where('held', false)->count() < $cap) {
            return false;
        }

        // Once a day, the people who manage the channel are told: how many, never what.
        if (Cache::add("complaints:cap-notified:{$channel->id}:{$day}", true, now()->addDay())) {
            $company = $this->companies->current();

            foreach ($this->members->complaintManagers() as $manager) {
                Notification::route('mail', $manager->email)->notify(new DailyCapReached($company, $cap, route('tenant.complaints.channel.show', $company->slug)));
            }

            Log::info('complaints.cap_reached', ['channel_id' => $channel->id]);
        }

        // The held ones are bounded too, so the table is.
        if ((clone $today)->where('held', true)->count() >= config()->integer('complaints.held_cap')) {
            throw new ChannelOverCap;
        }

        return true;
    }
}
