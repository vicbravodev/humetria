import RoomSessionEnd from '@/components/demos/room-session-end';
import { Badge } from '@/components/ui/badge';
import { sessionTitle, tourNames } from '@/lib/demos/room';
import type { DemoRoomSession } from '@/types';

/** A session whose preparation failed: nobody of the audience reached it; ending it leaves the demo clean for another. */
export default function RoomSessionFailed({
    session,
}: {
    session: DemoRoomSession;
}) {
    return (
        <section
            aria-labelledby="room-session"
            className="flex max-w-3xl flex-col gap-4 card p-5 md:p-6"
        >
            <div className="flex flex-wrap items-center gap-3">
                <h2 id="room-session" className="text-lg font-semibold">
                    {sessionTitle(session.client)}
                </h2>
                <Badge variant="destructive">Falló</Badge>
            </div>
            <p className="text-sm text-pretty text-muted-foreground">
                {`${tourNames(session.tours)}. Nadie del público llegó a darse de alta.`}
            </p>
            <div>
                <RoomSessionEnd client={session.client} variant="default" />
            </div>
        </section>
    );
}
