import { Head } from '@inertiajs/react';
import HomeScreen from '@/components/worker-access/home-screen';
import type { WorkerSection } from '@/types';

/** The person's start, with their session open. */
export default function Home(props: {
    company: string;
    name: string;
    slug: string;
    sections: WorkerSection[];
    keyUrl: string | null;
    hasKey: boolean;
    leaveUrl: string;
}) {
    return (
        <>
            <Head title="Mi portal" />
            <HomeScreen {...props} />
        </>
    );
}
