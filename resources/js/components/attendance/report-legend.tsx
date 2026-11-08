import type { AttendanceReportProps } from '@/types';

/** How to read the report: what it holds, how its hours are split and what is left out. */
export default function ReportLegend({
    generatedAt,
}: Pick<AttendanceReportProps, 'generatedAt'>) {
    return (
        <section
            aria-labelledby="como-leer"
            className="flex flex-col gap-3 card p-6 text-sm text-muted-foreground"
        >
            <h2
                id="como-leer"
                className="text-lg font-semibold text-foreground"
            >
                Cómo leer este reporte
            </h2>
            <ul className="flex list-disc flex-col gap-2 pl-5 text-pretty">
                <li>
                    Las horas son de la Ciudad de México (America/Mexico_City).
                    Un día es de la fecha de su inicio y del centro donde
                    empezó: un turno de noche de domingo a lunes es del domingo.
                </li>
                <li>
                    Solo aparecen los días con marcas: los días sin marcas no
                    aparecen, aunque su horario los programe.
                </li>
                <li>
                    El tiempo extra de un día es lo trabajado sobre lo
                    programado ese día; lo ordinario que pasa del máximo semanal
                    del año también es extra, y lo trabajado en día de descanso
                    va aparte (criterio 2.9). El tope semanal de extra se paga
                    doble y lo que lo rebasa, triple: el tope se llena con el
                    extra más temprano de la semana, día por día, desde el
                    lunes, aunque el periodo empiece después.
                </li>
                <li>
                    La evidencia de un día es la más débil de sus marcas: Fuerte
                    (QR y ubicación), Media (solo uno de los dos), Básica
                    (remoto o navegador de confianza) o Corregida.
                </li>
                <li>
                    Las marcas no se editan ni se borran: este reporte es una
                    vista de ese registro. Cada corrección dice qué cambió
                    («hora corregida de 08:12 a 08:00», «marca agregada» o
                    «marca anulada», con la hora en que estaba), su motivo y si
                    la pidió la persona o la hizo RH; la marca original queda
                    guardada.
                </li>
                <li>
                    Las horas se reparten con el horario vigente de cada persona
                    al generar el reporte.
                </li>
                <li>
                    «Sin confirmar» es una semana que la persona no respondió:
                    el silencio no es conformidad.
                </li>
                <li>
                    Abrir el reporte de un centro queda en la bitácora (su
                    primera página; pasar a las siguientes no se vuelve a
                    anotar) y cada descarga también, con el centro, el periodo y
                    el número de renglones.
                </li>
            </ul>
            {generatedAt !== null && (
                <p className="text-caption">
                    Generado el {generatedAt.local} (hora de la Ciudad de
                    México), {generatedAt.utc} UTC.
                </p>
            )}
        </section>
    );
}
