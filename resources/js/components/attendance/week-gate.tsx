/** Before anything of the person shows: their week opens only once their device verifies them (the button is at the foot). */
export default function WeekGate() {
    return (
        <section aria-label="Ver mi semana" className="flex flex-col card p-5">
            <p className="text-base">
                Para ver sus marcas y sus horas, confirme que es usted con su
                huella, su rostro o su PIN. Nada de su semana se guarda en este
                teléfono.
            </p>
        </section>
    );
}
