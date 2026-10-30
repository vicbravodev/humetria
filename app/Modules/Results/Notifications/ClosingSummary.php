<?php

declare(strict_types=1);

namespace App\Modules\Results\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The owner's email when an evaluation closes: participation, the questionnaires at each risk level (only when the
 * group reaches the minimum) and what follows.
 */
final class ClosingSummary extends Notification implements ShouldQueue
{
    use Queueable;

    /** The colors of the risk levels in the design system, Nulo to Muy alto. */
    private const array COLORS = ['#5a9c91', '#2b7f74', '#a47f2c', '#bc6a48', '#9a3b26'];

    private const array LABELS = ['Nulo', 'Bajo', 'Medio', 'Alto', 'Muy alto'];

    /**
     * @param  list<int>|null  $levels  questionnaires at each level, Nulo to Muy alto; null below the minimum group.
     * @param  array{label: string, high: int}|null  $domain  the domain with the most questionnaires at Alto or Muy alto.
     * @param  bool  $hasRisk  whether the campaign applied a guide scored by risk level (Guía II or III).
     */
    public function __construct(
        private readonly string $tradeName,
        private readonly string $workCenter,
        private readonly int $answered,
        private readonly int $invited,
        private readonly ?array $levels,
        private readonly ?array $domain,
        private readonly bool $hasRisk,
        private readonly int $requiringAssessment,
        private readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $high = $this->levels === null ? null : ($this->levels[3] ?? 0) + ($this->levels[4] ?? 0);
        $participation = $this->invited === 0 ? 0 : (int) round(100 * $this->answered / $this->invited);
        $domainShare = $this->domain === null || $this->answered === 0 ? null : (int) round(100 * $this->domain['high'] / $this->answered);

        return (new MailMessage)
            ->subject($high === null
                ? "{$this->workCenter} cerró con {$this->answered} de {$this->invited} respuestas"
                : "{$this->workCenter} cerró: {$high} de {$this->answered} en riesgo alto o muy alto")
            ->action('Ver resultados', $this->url)
            ->markdown('mail.results.closed', [
                'preheader' => "Participación de {$participation} %.".($this->domain === null ? '' : " {$this->domain['label']} es el dominio que más pesa."),
                'workCenter' => $this->workCenter,
                'tradeName' => $this->tradeName,
                'answered' => $this->answered,
                'invited' => $this->invited,
                'participation' => $participation,
                'high' => $high,
                'domain' => $this->domain,
                'domainShare' => $domainShare,
                'levels' => $this->levels === null ? null : array_map(
                    fn (int $index): array => ['label' => self::LABELS[$index], 'count' => $this->levels[$index] ?? 0, 'color' => self::COLORS[$index]],
                    array_keys(self::LABELS),
                ),
                'belowMinimum' => $this->hasRisk && $this->levels === null,
                'minGroup' => config()->integer('results.min_group'),
                'assessment' => $this->levels === null && $this->hasRisk ? 0 : $this->requiringAssessment,
                'report' => $this->levels !== null,
            ]);
    }
}
