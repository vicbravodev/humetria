import { LogOut } from 'lucide-react';
import LegalHoldSwitch from '@/components/complaints/legal-hold-switch';
import ShareCaseDialog from '@/components/complaints/share-case-dialog';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { recuse } from '@/routes/tenant/complaints/cases';

/**
 * «Si no te corresponde» (board V4-Quejas-Caso): excuse yourself (the case stays with the other handlers and you stop seeing
 * it), share it with one the conflict leaves, and keep it for a legal process.
 */
export default function CaseConflict({
    caseId,
    candidates,
    onHold,
}: {
    caseId: string;
    candidates: Option[];
    onHold: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <section
            aria-labelledby="case-conflict"
            className="flex flex-col gap-4 card p-6"
        >
            <h2 id="case-conflict" className="text-lg font-semibold">
                Si no te corresponde
            </h2>
            <p className="text-sm text-muted-foreground">
                Si conoces a quien está involucrada o tienes un conflicto,
                excúsate. El caso sigue con las demás personas designadas y tú
                dejas de verlo.
            </p>
            <div className="flex flex-wrap gap-2">
                <ConfirmDialog
                    title="¿Excusarte de este caso?"
                    description="Declaras un conflicto de interés: dejas de ver el caso y queda anotado en su bitácora. Si nadie más lo tiene, quien administra el canal lo asigna de nuevo, nunca a ti."
                    confirmLabel="Excusarme"
                    form={recuse.form({
                        tenant: tenantSlug,
                        complaint: caseId,
                    })}
                    trigger={
                        <Button variant="outline">
                            <LogOut aria-hidden />
                            Excusarme de este caso
                        </Button>
                    }
                />
                <ShareCaseDialog caseId={caseId} candidates={candidates} />
            </div>
            <div className="border-t pt-4">
                <LegalHoldSwitch caseId={caseId} onHold={onHold} />
            </div>
        </section>
    );
}
