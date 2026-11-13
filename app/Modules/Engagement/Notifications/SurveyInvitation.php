<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that carries a person's survey link, from their company and written as the survey speaks («usted»): the
 * invitation and, with the same link, each reminder. The plain token lives only here and in the link.
 */
final class SurveyInvitation extends Notification implements MailBag
{
    public function __construct(
        private readonly bool $reminder,
        private readonly string $token,
        private readonly string $name,
        private readonly CompanySummary $company,
        private readonly ?string $contact,
        private readonly CarbonImmutable $closesOn,
        private readonly SurveyKind $kind,
    ) {}

    /**
     * Every channel the person was routed on.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_values(array_filter(array_keys($notifiable->routes), is_string(...))) : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lastDay = $this->closesOn->isoFormat('D [de] MMMM');
        // The last day, not a count of days: the panel counts from tomorrow and a count here said another number (as in NOM-035).
        $title = SurveyClock::daysToAnswer($this->closesOn) <= 1 ? 'Hoy es su último día para dar su opinión' : 'Tiene hasta el '.$this->closesOn->isoFormat('dddd D [de] MMMM').' para dar su opinión';

        return (new MailMessage)
            ->subject($this->reminder ? $title : "Su opinión sobre el trabajo en {$this->company->tradeName}")
            ->action('Contestar la encuesta', route('engagement.survey.show', $this->token))
            ->markdown($this->reminder ? 'mail.engagement.reminder' : 'mail.engagement.invitation', [
                'name' => $this->name,
                'company' => $this->company,
                'contact' => $this->contact,
                'minutes' => $this->kind->minutes(),
                'minGroup' => config()->integer('results.min_group'),
                'title' => $title,
                'lastDay' => $lastDay,
            ]);
    }

    public function mailBag(): string
    {
        return MailBag::CAMPAIGN;
    }
}
