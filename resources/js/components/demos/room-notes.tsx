/** What the presenter should know before handing the demo to an audience. */
export default function RoomNotes() {
    return (
        <section
            aria-labelledby="room-notes"
            className="flex flex-col gap-3 card p-5 md:col-span-2"
        >
            <h2 id="room-notes" className="text-base font-semibold">
                Antes de presentar
            </h2>
            <ul className="flex list-disc flex-col gap-2 pl-5 text-sm text-pretty text-muted-foreground">
                <li>
                    Reinicia la demo a lo más dos días antes: después, una
                    denuncia queda vencida, las solicitudes de vacaciones
                    pendientes se escalan (a los cuatro días hábiles) y las
                    encuestas abiertas cierran (a los nueve días).
                </li>
                <li>
                    El canal de denuncias de la demo acepta más envíos por hora
                    que el de una empresa real: un salón entero comparte la
                    misma red.
                </li>
                <li>
                    Marcar asistencia en vivo necesita un teléfono de verdad:
                    cada quien lo vincula desde «Mi jornada» en su portal.
                </li>
                <li>
                    Cada enlace de encuesta sembrado sirve una sola vez por
                    persona: quien ya contestó no puede volver a hacerlo.
                </li>
            </ul>
        </section>
    );
}
