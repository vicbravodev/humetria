import { UserRound } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import RequestLinkButton from '@/components/seating/request-link-button';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { resend } from '@/routes/tenant/seating/acknowledgments';
import type { AssessmentRecord } from '@/types';

/** The person assessed, the state of the assessment and their position; once signed, the link to send them again. */
export default function AssessmentHeader({
    person,
    position,
    assessment,
    canResend,
}: {
    person: string;
    position: string;
    assessment: AssessmentRecord;
    canResend: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <PageHeader
            title={person}
            status={
                <Badge
                    variant={
                        assessment.status === 'signed' ? 'secondary' : 'outline'
                    }
                >
                    {assessment.statusLabel}
                </Badge>
            }
            facts={[{ icon: UserRound, text: position }]}
        >
            {canResend && (
                <RequestLinkButton
                    form={resend.form([tenantSlug, assessment.id])}
                    label="Reenviar enlace a la persona"
                />
            )}
        </PageHeader>
    );
}
