import { Head } from '@inertiajs/react';
import PersonFrame from '@/components/attendance/person-frame';

/** A person marks their working day from their linked device (no account). */
export default function Punch({ company }: { company: string }) {
    return (
        <PersonFrame company={company}>
            <Head title="Marcar" />
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Marcar
            </h1>
        </PersonFrame>
    );
}
