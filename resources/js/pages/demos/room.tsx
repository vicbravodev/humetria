import { Head } from '@inertiajs/react';
import RoomNotices from '@/components/demos/room-notices';
import RoomPoll from '@/components/demos/room-poll';
import RoomSession from '@/components/demos/room-session';
import RoomStatus from '@/components/demos/room-status';
import RoomTools from '@/components/demos/room-tools';
import PageHeader from '@/components/humetria/page-header';
import { roomDescription } from '@/lib/demos/room';
import type { DemoRoom } from '@/types';

/** «Sala de demostración»: a session for a client on top, guided in the demo company; the room's tools under it. */
export default function Room(props: DemoRoom) {
    const { company, run, session } = props;
    const running = run?.status === 'running';

    return (
        <>
            <Head title="Sala de demostración" />
            {(running ||
                session?.status === 'preparing' ||
                session?.status === 'ending') && <RoomPoll />}
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Consola · solo superadmin"
                    title="Sala de demostración"
                    status={
                        session === null && (
                            <RoomStatus run={run} company={company} />
                        )
                    }
                    description={roomDescription(props)}
                />
                <RoomNotices
                    run={run}
                    prepared={props.prepared}
                    session={session}
                />
                <RoomSession
                    session={session}
                    tours={props.tours}
                    run={run}
                    company={company}
                />
                {/* A failed session leaves the demo half made: nothing to enter or share until it ends. */}
                {session?.status !== 'failed' && (
                    <RoomTools
                        company={company}
                        complaints={props.complaints}
                        door={props.door}
                        session={session}
                        running={running}
                    />
                )}
            </div>
        </>
    );
}
