import { Head } from '@inertiajs/react';
import ChecklistForm from '@/components/telework/checklist-form';
import PortalShell from '@/components/telework/portal/portal-shell';
import PortalUnavailable from '@/components/telework/portal/portal-unavailable';
import type { PortalApplication, PortalQuestion } from '@/types';

export default function PortalChecklist({
    unavailable,
    token,
    company,
    application,
    instructions,
    notice,
    consent,
    questions,
}: {
    unavailable: boolean;
    token: string | null;
    company: string | null;
    application: PortalApplication | null;
    instructions: string;
    notice: string;
    consent: { text: string } | null;
    questions: PortalQuestion[];
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su lista de verificación" />
            {unavailable ||
            token === null ||
            application === null ||
            consent === null ? (
                <div className="w-full max-w-2xl">
                    <PortalUnavailable />
                </div>
            ) : (
                <ChecklistForm
                    token={token}
                    application={application}
                    instructions={instructions}
                    notice={notice}
                    consent={consent.text}
                    questions={questions}
                />
            )}
        </PortalShell>
    );
}
