import { Form } from '@inertiajs/react';
import { RotateCcw, SearchCheck } from 'lucide-react';
import CaseAcknowledge from '@/components/complaints/case-acknowledge';
import CaseReply from '@/components/complaints/case-reply';
import CloseCaseDialog from '@/components/complaints/close-case-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reopen, review } from '@/routes/tenant/complaints/cases';
import type { CaseCan, CaseResolutionOption } from '@/types';

/**
 * What comes next in a case (board V4-Quejas-Caso), one step at a time: acknowledge it with the draft, start the review,
 * write and close with an answer, or open it again once closed.
 */
export default function CaseNext({
    caseId,
    can,
    draft,
    resolutions,
}: {
    caseId: string;
    can: CaseCan;
    draft: string;
    resolutions: CaseResolutionOption[];
}) {
    const tenantSlug = useTenantSlug();
    const target = { tenant: tenantSlug, complaint: caseId };

    if (can.acknowledge) {
        return <CaseAcknowledge caseId={caseId} draft={draft} />;
    }

    if (can.reopen) {
        return (
            <Form {...reopen.form(target)} options={{ preserveScroll: true }}>
                <Button variant="outline">
                    <RotateCcw aria-hidden />
                    Abrir de nuevo
                </Button>
            </Form>
        );
    }

    return (
        <>
            {can.review && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <p className="font-medium">
                            Siguiente: revisar lo que pasó
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Habla con quien corresponda y reúne información. Lo
                            que anotes en las notas internas no lo ve la
                            persona.
                        </p>
                    </div>
                    <Form
                        {...review.form(target)}
                        options={{ preserveScroll: true }}
                    >
                        <Button>
                            <SearchCheck aria-hidden />
                            Empezar la revisión
                        </Button>
                    </Form>
                </div>
            )}
            {can.write && <CaseReply caseId={caseId} />}
            {can.close && !can.review && (
                <CloseCaseDialog caseId={caseId} resolutions={resolutions} />
            )}
        </>
    );
}
