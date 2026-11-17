import { Head } from '@inertiajs/react';
import DoorMessage from '@/components/demos/door-message';
import type { DoorState } from '@/components/demos/door-message';

/** The door of a live demo when it lets nobody in: a closed, expired or unknown code all read the same. */
export default function DemoDoor({ state }: { state: DoorState }) {
    return (
        <>
            <Head title="Demostración en vivo" />
            <DoorMessage state={state} />
        </>
    );
}
