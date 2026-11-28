<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Carbon\CarbonInterface;

/**
 * The label of time on a request's card, with how urgent it looks (a Badge variant): a new one by the hours it has
 * waited (past 24 h it warns, past 48 h it alarms), a contacted one by the days since, a scheduled one by its day.
 */
final class DemoCardTime
{
    /** @return array{label: string, tone: string} */
    public function of(DemoRequest $request): array
    {
        return match ($request->status) {
            DemoStatus::New => $this->waited($request->created_at),
            DemoStatus::Contacted => $this->contacted($request->handled_at ?? $request->created_at),
            DemoStatus::Scheduled => $request->scheduled_at === null
                ? ['label' => 'Sin fecha', 'tone' => 'warning']
                : ['label' => str_replace('.', '', $this->local($request->scheduled_at)->isoFormat('ddd D MMM, HH:mm')), 'tone' => 'default'],
            DemoStatus::Trial => ['label' => 'Llegó '.$this->daysAgo($request->created_at), 'tone' => 'secondary'],
            DemoStatus::Converted => ['label' => 'Convertida', 'tone' => 'success'],
            DemoStatus::Discarded => ['label' => 'Descartada', 'tone' => 'secondary'],
        };
    }

    /** @return array{label: string, tone: string} */
    private function waited(CarbonInterface $since): array
    {
        $hours = (int) floor($since->diffInHours(now()));
        $tone = $hours >= 48 ? 'destructive' : ($hours >= 24 ? 'warning' : 'secondary');

        return match (true) { // @pest-mutate-ignore: TrueToFalse
            $hours < 1 => ['label' => 'Hace menos de 1 h', 'tone' => $tone],
            $hours < 72 => ['label' => "Hace {$hours} h", 'tone' => $tone],
            default => ['label' => 'Hace '.intdiv($hours, 24).' d', 'tone' => $tone],
        };
    }

    /** @return array{label: string, tone: string} */
    private function contacted(CarbonInterface $at): array
    {
        return ['label' => 'Contactada '.$this->daysAgo($at), 'tone' => $this->days($at) >= 3 ? 'warning' : 'success'];
    }

    private function daysAgo(CarbonInterface $at): string
    {
        return match ($days = $this->days($at)) {
            0 => 'hoy',
            1 => 'ayer', // @pest-mutate-ignore: DecrementInteger,IncrementInteger
            default => "hace {$days} d",
        };
    }

    /** Calendar days in Mexico City between then and today. */
    private function days(CarbonInterface $at): int
    {
        return (int) round($this->local($at)->startOfDay()->diffInDays($this->local(now())->startOfDay()));
    }

    private function local(CarbonInterface $at): CarbonInterface
    {
        return $at->copy()->setTimezone(config()->string('app.display_timezone'));
    }
}
