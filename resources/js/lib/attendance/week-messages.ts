/** The words for each reason a week or a correction could not be read or answered, as «usted». */
const MESSAGES: Record<string, string> = {
    not_verified:
        'No se pudo verificar su dispositivo. Si aún no lo vincula, pida a Recursos Humanos su enlace para vincularlo.',
    cancelled:
        'No se confirmó su huella, su rostro o su PIN. Intente de nuevo.',
    throttled: 'Hubo demasiados intentos. Espere un minuto e intente de nuevo.',
    week_not_over:
        'Esta semana aún no termina. Podrá confirmarla u objetarla cuando termine el domingo.',
    week_disputed:
        'Usted objetó esta semana: Recursos Humanos la revisará antes de que pueda confirmarla.',
    already_disputed:
        'Ya objetó esta semana. Recursos Humanos revisará su motivo.',
    week_changed:
        'Su semana cambió desde que la abrió: Recursos Humanos corrigió sus marcas. Revísela de nuevo antes de responder.',
    day_open:
        'Su último turno de esta semana sigue abierto. Podrá confirmar su semana cuando marque su salida.',
    no_marks:
        'Esta semana no tiene marcas suyas. Si faltan, use «No estoy de acuerdo» para decirlo.',
    invalid:
        'Escriba qué no está bien en su semana, con al menos 10 caracteres.',
    unsupported:
        'Este navegador no puede usar llaves de acceso. Abra la página en el navegador de su teléfono.',
    future_time: 'La hora que propone aún no llega. Revise la hora y el día.',
    too_old:
        'Solo se pueden corregir marcas de los últimos 35 días. Hable con Recursos Humanos.',
    type_mismatch: 'Esa marca es de otro tipo. Intente de nuevo.',
    already_requested:
        'Ya pidió corregir esta marca. Recursos Humanos la está revisando.',
    too_many_pending:
        'Tiene 10 solicitudes esperando a Recursos Humanos. Espere a que las revisen antes de pedir otra.',
    reason_invalid:
        'Escriba su motivo en texto simple, con 500 caracteres a lo más.',
    invalid_request:
        'Escriba por qué pide la corrección, con al menos 10 caracteres, y la hora.',
};

/** What to tell the person when the week could not be read or answered. */
export function weekMessage(reason: string): string {
    return MESSAGES[reason] ?? 'No se pudo completar. Intente de nuevo.';
}
