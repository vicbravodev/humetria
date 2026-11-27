<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Attendance\Contracts\AttendanceActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\WorkerAccess\Contracts\WorkerAccessActivity;
use LogicException;

/**
 * Asistencia: the session's center gets its mode, zone and kiosk; the audience gets its access, creates its key and marks.
 * Accesses and keys are the company's since the session started (the contract does not tell whose), so the copy never
 * promises «esa persona».
 */
final readonly class AttendanceTour implements Tour
{
    public function __construct(private AttendanceActivity $attendance, private WorkerAccessActivity $access) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'attendance.mode', TourKey::Attendance, 'Escoge cómo se marca',
                'Escoge el modo del centro: con el QR del kiosco o sin él.',
                'Cada centro marca como le funciona. Una oficina marca con el QR de la entrada; quien trabaja en campo, desde donde esté.',
                'do', 'tenant.attendance.center', 'attendance-center-mode',
            ),
            new TourStep(
                'attendance.zone', TourKey::Attendance, 'Dibuja la zona',
                'Dibuja en el mapa la zona donde se puede marcar.',
                'Fuera de la zona, la marca se bloquea o queda señalada para revisión, según la política del centro.',
                'do', 'tenant.attendance.center', 'attendance-add-zone',
            ),
            new TourStep(
                'attendance.kiosk', TourKey::Attendance, 'Emite el kiosco',
                'Emite el kiosco del centro para mostrar su QR en una pantalla.',
                'El QR cambia solo, así que una foto vieja del código no sirve para marcar.',
                'do', 'tenant.attendance.center', 'attendance-issue-kiosk',
            ),
            new TourStep(
                'attendance.access', TourKey::Attendance, 'Envía el acceso al portal',
                'Envía a cada persona del público su acceso al portal.',
                'Cada persona entra a su portal sin contraseña, con un enlace que solo sirve una vez.',
                'do', 'tenant.worker-access.index', 'send-worker-access',
            ),
            new TourStep(
                'attendance.key', TourKey::Attendance, 'El público crea su llave',
                'Pide al público que abra su acceso y cree su llave con la huella o la cara.',
                'La llave vive en su celular, con su huella o su cara, en lugar de una contraseña que se presta.',
                'watch', 'tenant.worker-access.index', null,
            ),
            new TourStep(
                'attendance.mark', TourKey::Attendance, 'El público marca',
                'Pide al público que marque su entrada desde su celular.',
                'Cada marca llega con su hora y su lugar, lista para el registro de jornada.',
                'watch', 'tenant.attendance.center', null,
            ),
            new TourStep(
                'attendance.report', TourKey::Attendance, 'Mira el reporte',
                'Abre el reporte de asistencia.',
                'RH ve las horas de todo el centro en un solo lugar, listas para la nómina.',
                'watch', 'tenant.attendance.report', null,
                '¿Cómo va la asistencia de esta semana?',
            ),
        ];
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        if ($session->work_center_id === null) {
            return StepState::pending();
        }

        return match ($step->key) {
            'attendance.mode' => new StepState($this->attendance->atCenter($session->work_center_id, $session->since())->modeSet),
            'attendance.zone' => new StepState($this->attendance->atCenter($session->work_center_id, $session->since())->zones >= 1),
            'attendance.kiosk' => new StepState($this->attendance->atCenter($session->work_center_id, $session->since())->kiosks >= 1),
            'attendance.access' => StepState::counted($this->access->since($session->since())->invited, '1 acceso enviado', 'accesos enviados'),
            'attendance.key' => StepState::counted($this->access->since($session->since())->keys, '1 llave creada', 'llaves creadas'),
            'attendance.mark' => StepState::counted($this->attendance->atCenter($session->work_center_id, $session->since())->marks, '1 marca en el centro', 'marcas en el centro'),
            'attendance.report' => new StepState(in_array('tenant.attendance.report', $session->visited, true)),
            default => throw new LogicException("Unknown step {$step->key}."),
        };
    }
}
