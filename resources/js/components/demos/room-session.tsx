import RoomSessionActive from '@/components/demos/room-session-active';
import RoomSessionFailed from '@/components/demos/room-session-failed';
import RoomSessionNew from '@/components/demos/room-session-new';
import RoomSessionPreparing from '@/components/demos/room-session-preparing';
import type { DemoRoom } from '@/types';

/** The top of the room: a new session to start, or the one in course as it stands. */
export default function RoomSession({
    session,
    tours,
    run,
    company,
}: Pick<DemoRoom, 'session' | 'tours' | 'run' | 'company'>) {
    if (session === null) {
        return (
            <RoomSessionNew tours={tours} running={run?.status === 'running'} />
        );
    }

    if (session.status === 'failed') {
        return <RoomSessionFailed session={session} />;
    }

    if (session.status === 'active' && company !== null) {
        return <RoomSessionActive session={session} companyId={company.id} />;
    }

    return (
        <RoomSessionPreparing
            session={session}
            step={run?.status === 'running' ? run.step : null}
        />
    );
}
