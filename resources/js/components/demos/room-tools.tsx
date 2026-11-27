import RoomEmpty from '@/components/demos/room-empty';
import RoomToolCards from '@/components/demos/room-tool-cards';
import type { DemoRoom } from '@/types';

/**
 * «Herramientas de la sala», under the session: entering without the guide, the door, the complaint channel and the
 * time-clock screens. Held while a session is prepared or ends; «Reiniciar demo» only without a session.
 */
export default function RoomTools({
    company,
    complaints,
    door,
    session,
    running,
}: Pick<DemoRoom, 'company' | 'complaints' | 'door' | 'session'> & {
    running: boolean;
}) {
    const held =
        session?.status === 'preparing' || session?.status === 'ending';

    return (
        <section aria-labelledby="room-tools" className="flex flex-col gap-4">
            <div className="flex flex-col gap-1">
                <h2 id="room-tools" className="text-lg font-semibold">
                    Herramientas de la sala
                </h2>
                <p className="text-sm text-pretty text-muted-foreground">
                    {held
                        ? 'Disponibles cuando la sesión esté lista.'
                        : 'Para entrar sin guía o para públicos grandes.'}
                </p>
            </div>
            {!held && company === null && <RoomEmpty running={running} />}
            {!held && company !== null && (
                <RoomToolCards
                    companyId={company.id}
                    tools={{ complaints, door }}
                    reset={session === null}
                    running={running}
                />
            )}
        </section>
    );
}
