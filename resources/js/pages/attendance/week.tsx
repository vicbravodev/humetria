import { Head } from '@inertiajs/react';
import PersonFrame from '@/components/attendance/person-frame';

/** «Mi semana»: a person sees one of their weeks after their device signs. */
export default function Week({ company }: { company: string }) {
    return (
        <PersonFrame company={company}>
            <Head title="Mi semana" />
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Mi semana
            </h1>
        </PersonFrame>
    );
}
