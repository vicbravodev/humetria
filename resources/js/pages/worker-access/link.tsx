import { Head } from '@inertiajs/react';
import LinkContinue from '@/components/worker-access/link-continue';
import LinkProblem from '@/components/worker-access/link-problem';

/** A one-time link opened: «Continuar» while it works; one screen for every link that does not. */
export default function AccessLink({
    company,
    openUrl,
    homeUrl,
}: {
    company: string;
    /** Null when the link does not work. */
    openUrl: string | null;
    homeUrl: string;
}) {
    return (
        <>
            <Head title="Entrar a mi portal" />
            {openUrl === null ? (
                <LinkProblem company={company} homeUrl={homeUrl} />
            ) : (
                <LinkContinue company={company} openUrl={openUrl} />
            )}
        </>
    );
}
