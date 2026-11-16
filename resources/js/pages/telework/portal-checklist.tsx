import { Head } from '@inertiajs/react';
import ChecklistForm from '@/components/telework/checklist-form';
import PortalShell from '@/components/telework/portal/portal-shell';
import type { PortalApplication, PortalQuestion } from '@/types';

export default function PortalChecklist({
    slug,
    company,
    application,
    instructions,
    notice,
    consent,
    questions,
}: {
    slug: string;
    company: string;
    application: PortalApplication;
    instructions: string;
    notice: string;
    consent: { text: string };
    questions: PortalQuestion[];
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su lista de verificación" />
            <ChecklistForm
                slug={slug}
                application={application}
                instructions={instructions}
                notice={notice}
                consent={consent.text}
                questions={questions}
            />
        </PortalShell>
    );
}
