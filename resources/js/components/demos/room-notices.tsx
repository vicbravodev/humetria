import RoomProgress from '@/components/demos/room-progress';
import RoomRunFailed from '@/components/demos/room-run-failed';
import Notice from '@/components/humetria/notice';
import type { DemoRoom } from '@/types';

/**
 * Under the title: the run going on, the last one that failed, or a demo too old to present. A session's own card tells
 * its run: here only why its preparation failed.
 */
export default function RoomNotices({
    run,
    prepared,
    session,
}: Pick<DemoRoom, 'run' | 'prepared' | 'session'>) {
    if (run?.status === 'running') {
        return session === null ? <RoomProgress step={run.step} /> : null;
    }

    if (run?.status === 'failed') {
        return <RoomRunFailed run={run} session={session?.failed === true} />;
    }

    if (prepared?.stale === true) {
        return (
            <Notice>
                <strong>{`La demo se preparó ${prepared.ago}.`}</strong> Sus
                datos ya envejecieron: reiníciala antes de presentar.
            </Notice>
        );
    }

    return null;
}
