import { Head } from '@inertiajs/react';
import EntryScreen from '@/components/worker-access/entry-screen';
import { useWorkerEntry } from '@/hooks/use-worker-entry';

/** The person's entrance without a session: their key, or a link by email. It names nobody. */
export default function Enter({
    company,
    optionsUrl,
    enterUrl,
    linkUrl,
}: {
    company: string;
    optionsUrl: string;
    enterUrl: string;
    linkUrl: string;
}) {
    const entry = useWorkerEntry({ optionsUrl, enterUrl, linkUrl });

    return (
        <>
            <Head title="Entrar a mi portal" />
            <EntryScreen company={company} entry={entry} />
        </>
    );
}
