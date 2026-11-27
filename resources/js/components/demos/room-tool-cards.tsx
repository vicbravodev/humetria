import RoomComplaints from '@/components/demos/room-complaints';
import RoomDoor from '@/components/demos/room-door';
import RoomEnter from '@/components/demos/room-enter';
import RoomKiosk from '@/components/demos/room-kiosk';
import RoomNotes from '@/components/demos/room-notes';
import RoomReset from '@/components/demos/room-reset';
import type { DemoRoom } from '@/types';

/** The cards of the room's tools, with «Reiniciar demo» (`reset`) only while no session is open. */
export default function RoomToolCards({
    companyId,
    tools,
    reset,
    running,
}: {
    companyId: string;
    tools: Pick<DemoRoom, 'complaints' | 'door'>;
    reset: boolean;
    running: boolean;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <RoomEnter
                companyId={companyId}
                running={running}
                session={!reset}
            />
            <RoomDoor door={tools.door} />
            {tools.complaints !== null && (
                <RoomComplaints link={tools.complaints} />
            )}
            <RoomKiosk />
            {reset && <RoomReset running={running} />}
            <RoomNotes />
        </div>
    );
}
