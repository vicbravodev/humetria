import { Head } from '@inertiajs/react';
import HomeScreen from '@/components/worker-access/home-screen';

/** The person's start, with their session open. */
export default function Home(props: {
    company: string;
    name: string;
    keyUrl: string | null;
    leaveUrl: string;
}) {
    return (
        <>
            <Head title="Mi portal" />
            <HomeScreen {...props} />
        </>
    );
}
