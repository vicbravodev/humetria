import { Head } from '@inertiajs/react';
import LinkContinue from '@/components/worker-access/link-continue';
import LinkProblem from '@/components/worker-access/link-problem';

/** A one-time link opened: «Continuar» while it works; one screen for every link that does not. */
export default function AccessLink({
    company,
    opens,
    homeUrl,
}: {
    company: string;
    /** Whether the link still works; «Continuar» posts to this page's own address, so the token is in no prop. */
    opens: boolean;
    homeUrl: string;
}) {
    return (
        <>
            <Head title="Entrar a mi portal" />
            {opens ? (
                <LinkContinue company={company} />
            ) : (
                <LinkProblem company={company} homeUrl={homeUrl} />
            )}
        </>
    );
}
