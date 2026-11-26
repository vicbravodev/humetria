import { Head } from '@inertiajs/react';
import LinkContinue from '@/components/worker-access/link-continue';
import LinkProblem from '@/components/worker-access/link-problem';

/** A one-time link opened: «Continuar» while it works; one screen for every link that does not. */
export default function AccessLink({
    company,
    opens,
    firstUse,
    homeUrl,
}: {
    company: string;
    /** Whether the link still works; «Continuar» posts to this page's own address, so the token is in no prop. */
    opens: boolean;
    /** Whether it is the link RH issued: the person's first use, so «Continuar» is preceded by the three steps. */
    firstUse: boolean;
    homeUrl: string;
}) {
    return (
        <>
            <Head title="Entrar a mi portal" />
            {opens ? (
                <LinkContinue company={company} firstUse={firstUse} />
            ) : (
                <LinkProblem company={company} homeUrl={homeUrl} />
            )}
        </>
    );
}
