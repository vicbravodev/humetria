import { Head } from '@inertiajs/react';
import RoomComplaints from '@/components/demos/room-complaints';
import RoomDoor from '@/components/demos/room-door';
import RoomEmpty from '@/components/demos/room-empty';
import RoomEnter from '@/components/demos/room-enter';
import RoomKiosk from '@/components/demos/room-kiosk';
import RoomNotes from '@/components/demos/room-notes';
import RoomNotices from '@/components/demos/room-notices';
import RoomRunButton from '@/components/demos/room-run-button';
import RoomStatus from '@/components/demos/room-status';
import PageHeader from '@/components/humetria/page-header';
import type { DemoRoom } from '@/types';

/** «Sala de demostración»: prepare the demo company, enter it, and share it live with an audience. */
export default function Room({
    company,
    run,
    prepared,
    complaints,
    door,
}: DemoRoom) {
    const running = run?.status === 'running';

    return (
        <>
            <Head title="Sala de demostración" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Sala de demostración"
                    status={<RoomStatus run={run} company={company} />}
                    description={
                        company === null
                            ? 'La empresa demo para enseñar Humetría en vivo.'
                            : `${company.name}${prepared === null ? '' : ` · preparada ${prepared.ago} (${prepared.at})`}`
                    }
                >
                    {company !== null && (
                        <RoomRunButton kind="reset" running={running} />
                    )}
                </PageHeader>
                <RoomNotices run={run} prepared={prepared} />
                {company === null ? (
                    <RoomEmpty running={running} />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        <RoomEnter companyId={company.id} />
                        <RoomDoor door={door} />
                        {complaints !== null && (
                            <RoomComplaints link={complaints} />
                        )}
                        <RoomKiosk />
                        <RoomNotes />
                    </div>
                )}
            </div>
        </>
    );
}
