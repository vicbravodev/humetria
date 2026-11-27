import RoomRunButton from '@/components/demos/room-run-button';

/** «Reiniciar demo» without a session: a quiet card, since starting and ending a session already reset the demo. */
export default function RoomReset({ running }: { running: boolean }) {
    return (
        <section
            aria-labelledby="room-reset"
            className="flex flex-col gap-3 card p-5"
        >
            <h3 id="room-reset" className="text-base font-semibold">
                Reiniciar sin sesión
            </h3>
            <p className="text-sm text-pretty text-muted-foreground">
                Deja la demo limpia sin dar una sesión. Empezar y terminar una
                sesión ya la reinician.
            </p>
            <div>
                <RoomRunButton kind="reset" running={running} />
            </div>
        </section>
    );
}
