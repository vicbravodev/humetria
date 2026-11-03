import { Form } from '@inertiajs/react';
import {
    CircleCheck,
    Gavel,
    LogOut,
    RotateCcw,
    SearchCheck,
} from 'lucide-react';
import CloseCaseDialog from '@/components/complaints/close-case-dialog';
import PrintCaseButton from '@/components/complaints/print-case-button';
import ShareCaseDialog from '@/components/complaints/share-case-dialog';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import {
    acknowledge,
    hold,
    recuse,
    reopen,
    review,
} from '@/routes/tenant/complaints/cases';
import type { CaseCan, CaseResolutionOption } from '@/types';

/** What the handler can do with the case as it stands: acknowledge, review, share, step aside, hold, close or reopen, print. */
export default function CaseActions({
    caseId,
    can,
    onHold,
    candidates,
    resolutions,
}: {
    caseId: string;
    can: CaseCan;
    onHold: boolean;
    candidates: Option[];
    resolutions: CaseResolutionOption[];
}) {
    const tenantSlug = useTenantSlug();
    const target = { tenant: tenantSlug, complaint: caseId };
    const simple = (
        route: { form: (args: typeof target) => object },
        label: string,
        Icon: typeof CircleCheck,
    ) => (
        <Form {...route.form(target)} options={{ preserveScroll: true }}>
            <Button variant="outline">
                <Icon aria-hidden />
                {label}
            </Button>
        </Form>
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            {can.acknowledge &&
                simple(acknowledge, 'Acusar recibo', CircleCheck)}
            {can.review && simple(review, 'Pasar a revisión', SearchCheck)}
            {can.reopen && simple(reopen, 'Abrir de nuevo', RotateCcw)}
            <ShareCaseDialog caseId={caseId} candidates={candidates} />
            <ConfirmDialog
                title="¿Excusarte de este caso?"
                description="Declaras un conflicto de interés: dejas de ver el caso y queda anotado en su historial. Los demás que lo tienen siguen con él."
                confirmLabel="Excusarme"
                form={recuse.form(target)}
                trigger={
                    <Button variant="outline">
                        <LogOut aria-hidden />
                        Excusarme
                    </Button>
                }
            />
            <ConfirmDialog
                destructive={false}
                title={
                    onHold
                        ? '¿Levantar la retención legal?'
                        : '¿Poner el caso en retención legal?'
                }
                description={
                    onHold
                        ? 'El contenido volverá a borrarse al cumplirse el plazo de conservación.'
                        : 'Mientras dure, el contenido no se borra al vencer el plazo de conservación. Queda en la bitácora.'
                }
                confirmLabel={onHold ? 'Levantar' : 'Poner en retención'}
                form={hold.form(target)}
                transform={() => ({ on_hold: onHold ? '0' : '1' })}
                trigger={
                    <Button variant="outline">
                        <Gavel aria-hidden />
                        {onHold ? 'Levantar retención' : 'Retención legal'}
                    </Button>
                }
            />
            <PrintCaseButton caseId={caseId} />
            {can.close && (
                <CloseCaseDialog caseId={caseId} resolutions={resolutions} />
            )}
        </div>
    );
}
