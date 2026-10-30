<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Notifications;

use App\Modules\Campaigns\Enums\SurveyEmail;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's survey link, from their company and written as the survey speaks ("usted"). The
 * plain token lives only here and in the link.
 */
final class SurveyInvitation extends Notification
{
    public function __construct(
        private readonly SurveyEmail $email,
        private readonly string $token,
        private readonly string $name,
        private readonly CompanySummary $company,
        private readonly ?string $contact,
        private readonly string $workCenter,
        private readonly CarbonImmutable $closesOn,
        private readonly int $minutes,
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
        $lastDay = CarbonImmutable::parse($this->closesOn->format('Y-m-d'), config()->string('app.display_timezone'));
        // Counting today: on the last day there is one left.
        $daysLeft = (int) CampaignClock::today()->diffInDays($lastDay) + 1;
        [$subject, $action] = match ($this->email) {
            SurveyEmail::Invitation => ['Su opinión sobre su trabajo · evaluación confidencial', 'Contestar la evaluación'],
            SurveyEmail::Reminder => [self::daysLeft($daysLeft).' para contestar su evaluación', 'Continuar la evaluación'],
            SurveyEmail::Trauma => ['Una evaluación breve después de lo ocurrido', 'Contestar la Guía I'],
        };

        return (new MailMessage)
            ->subject($subject)
            ->action($action, route('survey.show', $this->token))
            ->markdown("mail.campaigns.{$this->email->value}", [
                'name' => $this->name,
                'company' => $this->company,
                'contact' => $this->contact,
                'workCenter' => $this->workCenter,
                'minutes' => $this->minutes,
                'minGroup' => config()->integer('results.min_group'),
                'title' => self::daysLeft($daysLeft).' para contestar',
                'lastDay' => $lastDay->isoFormat('D [de] MMMM'),
                'lastDayLong' => $lastDay->isoFormat('dddd D [de] MMMM'),
                'month' => mb_strtoupper(str_replace('.', '', $lastDay->isoFormat('MMM'))),
                'day' => $lastDay->day,
            ]);
    }

    private static function daysLeft(int $days): string
    {
        return $days <= 1 ? 'Hoy es su último día' : "Le quedan {$days} días";
    }
}
