<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Contracts\ComplaintsToAcknowledge;
use App\Modules\Complaints\Data\ComplaintToAcknowledge;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use Illuminate\Database\Eloquent\Builder;

/** New complaints of the current company held by the person and not acknowledged yet. */
final readonly class CompanyComplaintsToAcknowledge implements ComplaintsToAcknowledge
{
    public function count(int $userId): int
    {
        return $this->waiting($userId)->count();
    }

    public function soonest(int $userId, int $limit): array
    {
        $days = ComplaintChannel::query()->first()->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days');

        return array_values($this->waiting($userId)->orderBy('received_on')->orderBy('id')->limit($limit)->get(['id', 'type', 'received_on'])
            ->map(fn (Complaint $complaint): ComplaintToAcknowledge => new ComplaintToAcknowledge(
                $complaint->id,
                self::noun($complaint->type).' espera acuse de recibo',
                $complaint->received_on->addDays($days)->toDateString(),
            ))->all());
    }

    /** @return Builder<Complaint> */
    private function waiting(int $userId): Builder
    {
        return Complaint::query()
            ->whereIn('id', ComplaintAssignment::query()->where('user_id', $userId)->whereNull('ended_on')->select('complaint_id'))
            ->where('status', ComplaintStatus::New->value)
            ->whereNull('acknowledged_at');
    }

    private static function noun(ComplaintType $type): string
    {
        return match ($type) {
            ComplaintType::UnfavorablePractice => 'Una queja',
            ComplaintType::WorkplaceViolence => 'Una denuncia',
            ComplaintType::TraumaticEventNotice => 'Un aviso de acontecimiento traumático',
        };
    }
}
