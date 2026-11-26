import { Head } from '@inertiajs/react';
import HomeScreen from '@/components/worker-access/home-screen';
import type { ProofStep, WorkerDay, WorkerSection } from '@/types';

/** The person's start, with their session open. */
export default function Home(props: {
    company: string;
    name: string;
    slug: string;
    sections: WorkerSection[];
    keyUrl: string | null;
    hasKey: boolean;
    /** Where this phone prepares to sign marks without a connection; null for a company without the registro de jornada. */
    deviceKey: {
        optionsUrl: string;
        storeUrl: string;
        proof: ProofStep;
    } | null;
    /** Where Marcar is (the tab of the bar); null for a company without the registro de jornada. */
    punchUrl: string | null;
    /** Where their week is seen; null without attendance. */
    weekUrl: string | null;
    /** Their day, where the company keeps one. */
    day: WorkerDay | null;
    /** The server's time, in milliseconds. */
    now: number;
    leaveUrl: string;
}) {
    return (
        <>
            <Head title="Mi portal" />
            <HomeScreen {...props} />
        </>
    );
}
