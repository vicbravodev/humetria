<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AttendanceAgreement;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * The company's current agreement on the electronic record (criterio 5.1): what is recorded (the location only when the
 * person marks, criterio 3.4), who sees it, how it is corrected, how long it is kept (the policy's retention, criterio
 * 7.1) and its value as evidence (criterio 1.3), in plain words and «usted». Written as version 1 on first use; when the
 * text changes (the retention did), it is written as the next version, and the ones already accepted stay as they were.
 */
final readonly class CurrentAgreement
{
    /** The policy table's default, for a company that has not saved its policy yet. */
    private const int DEFAULT_RETENTION_MONTHS = 12;

    public function __construct(private CurrentTenant $tenant) {}

    public function get(): AttendanceAgreement
    {
        $body = self::text(AttendancePolicy::query()->first()->retention_months ?? self::DEFAULT_RETENTION_MONTHS);
        $latest = AttendanceAgreement::query()->orderByDesc('version')->first();

        if ($latest !== null && $latest->body === $body) {
            return $latest;
        }

        // Two first visits at once write the same version once: the second reads the first's.
        return AttendanceAgreement::query()->createOrFirst(
            ['tenant_id' => $this->tenant->id(), 'version' => ($latest->version ?? 0) + 1],
            ['body' => $body],
        );
    }

    private static function text(int $retentionMonths): string
    {
        return implode("\n\n", [
            'Cómo se registra. Usted registra el inicio y el fin de su jornada, y el inicio y el fin de sus pausas, con este dispositivo y su huella, su rostro o el PIN de su teléfono. La hora la pone el servidor, no su teléfono.',
            'Qué se guarda. El tipo de marca, la hora y la ubicación: las coordenadas, su precisión y la zona del centro de trabajo, tomadas solo en el momento en que usted marca. Nunca se toma su ubicación en otro momento ni en segundo plano.',
            'Quién lo ve. Usted siempre puede ver su propio registro. Además, solo lo ven Recursos Humanos y las personas que su empresa autorice.',
            'Cómo se corrige. Si una marca está mal, se pide una corrección. La corrección es una marca nueva que dice quién la hizo y por qué; la marca original no se borra ni se cambia. Cada semana usted revisa su registro y lo confirma u objeta.',
            "Cuánto tiempo se guarda. Durante su relación de trabajo y {$retentionMonths} meses después.",
            'Valor de prueba. Al aceptar, usted y su empresa acuerdan este registro, por lo que hará prueba plena, como dice el artículo 132, fracción XXXIV, de la Ley Federal del Trabajo.',
        ]);
    }
}
