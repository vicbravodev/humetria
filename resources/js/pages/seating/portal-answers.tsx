import { Head } from '@inertiajs/react';
import PortalAnswers from '@/components/seating/portal-answers';
import type { PortalCondition } from '@/types';

/** The two health questions of the person's Ley Silla page, voluntary and identified, on their own screens (finding 13). */
export default function SeatingPortalAnswers(page: {
    slug: string;
    company: string;
    notice: string;
    footwear: PortalCondition;
    discomfort: PortalCondition;
    /** The date of the person's last answer, if they ever sent one. */
    sentOn: string | null;
}) {
    return (
        <>
            <Head title="Sus respuestas · Trabajo de pie" />
            <PortalAnswers page={page} />
        </>
    );
}
