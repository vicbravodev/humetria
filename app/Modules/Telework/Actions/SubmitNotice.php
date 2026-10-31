<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Data\AddressData;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Events\TraumaticEventReported;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Support\OpenedPortal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The person sends one of their three written notices (numerals 6.3, 6.7 and 6.9; criterio C-26): written date, date it
 * happened (it may be earlier) and description, neither date in the future (Mexico City). Only a person with an open
 * period. A traumatic event is only a change of conditions; "temporary" is only an address change.
 * An address change brings the new address: it becomes a workplace with the checklist the person owes there (Sección II
 * for someone in the roster, Sección I for an aspirant; criterio C-14). The old workplaces are not touched here: they
 * are retired when the new one is validated (`ReviewWorkplace`), unless the change is temporary.
 *
 * The description is personal data: never logged.
 */
final readonly class SubmitNotice
{
    public function __construct(private AddWorkplace $addWorkplace) {}

    /**
     * @throws ValidationException
     */
    public function handle(OpenedPortal $portal, NoticeKind $kind, CarbonImmutable $writtenOn, CarbonImmutable $occurredOn, string $description, bool $traumaticEvent, ?AddressData $newAddress, bool $temporary, ?string $ip): WorkerNotice
    {
        $description = trim($description);
        $teleworkerId = $portal->teleworkerId;
        $this->validate($teleworkerId, $kind, $writtenOn, $occurredOn, $description, $traumaticEvent, $temporary, $newAddress);

        return DB::transaction(function () use ($portal, $teleworkerId, $kind, $writtenOn, $occurredOn, $description, $traumaticEvent, $newAddress, $temporary, $ip): WorkerNotice {
            $newWorkplaceId = $newAddress === null ? null : $this->newWorkplace($teleworkerId, $newAddress);

            $notice = WorkerNotice::query()->create([
                'tenant_id' => $portal->tenantId,
                'teleworker_id' => $teleworkerId,
                'kind' => $kind,
                'written_on' => $writtenOn->toDateString(),
                'occurred_on' => $occurredOn->toDateString(),
                'description' => $description,
                'traumatic_event' => $traumaticEvent,
                'temporary' => $temporary,
                'new_workplace_id' => $newWorkplaceId,
                'submitted_at' => now(),
                'ip' => $ip,
            ]);

            Log::info('telework.notice_submitted', ['notice_id' => $notice->id, 'teleworker_id' => $teleworkerId, 'kind' => $kind->value, 'traumatic_event' => $traumaticEvent, 'temporary' => $temporary]);

            if ($traumaticEvent) {
                $teleworker = Teleworker::query()->findOrFail($teleworkerId, ['id', 'work_center_id']);
                Event::dispatch(new TraumaticEventReported($portal->tenantId, $portal->employeeId, $teleworker->work_center_id));
            }

            return $notice;
        });
    }

    /**
     * @throws ValidationException
     */
    private function validate(?string $teleworkerId, NoticeKind $kind, CarbonImmutable $writtenOn, CarbonImmutable $occurredOn, string $description, bool $traumaticEvent, bool $temporary, ?AddressData $newAddress): void
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();
        $errors = [];

        if ($teleworkerId === null) {
            $errors['notice'] = 'Solo quien teletrabaja puede enviar avisos.';
        }

        if ($writtenOn->toDateString() > $today) {
            $errors['written_on'] = 'La fecha de elaboración no puede estar en el futuro.';
        }

        if ($occurredOn->toDateString() > $today) {
            $errors['occurred_on'] = 'La fecha en que ocurrió no puede estar en el futuro.';
        }

        if ($description === '') {
            $errors['description'] = 'Escriba la descripción.';
        }

        if ($traumaticEvent && $kind !== NoticeKind::ConditionChange) {
            $errors['traumatic_event'] = 'Solo una alteración de condiciones puede ser un acontecimiento traumático.';
        }

        if ($temporary && $kind !== NoticeKind::AddressChange) {
            $errors['temporary'] = 'Solo un cambio de domicilio puede ser temporal.';
        }

        if (($kind === NoticeKind::AddressChange) !== ($newAddress !== null)) {
            $errors['address'] = $kind === NoticeKind::AddressChange ? 'Escriba el nuevo domicilio.' : 'Solo un cambio de domicilio lleva un domicilio nuevo.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function newWorkplace(?string $teleworkerId, AddressData $address): string
    {
        $teleworker = Teleworker::query()->findOrFail($teleworkerId);

        return $this->addWorkplace->handle($teleworker, $address, $teleworker->status === TeleworkerStatus::Active ? Section::Two : Section::One)->id;
    }
}
