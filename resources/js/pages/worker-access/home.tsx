import { Head } from '@inertiajs/react';
import HomeScreen from '@/components/worker-access/home-screen';
import type { ProofStep, WorkerSection } from '@/types';

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
    leaveUrl: string;
}) {
    return (
        <>
            <Head title="Mi portal" />
            <HomeScreen {...props} />
        </>
    );
}
