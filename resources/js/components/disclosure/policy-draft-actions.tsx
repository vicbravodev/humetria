import { Link } from '@inertiajs/react';
import { FilePlus2, PenLine } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy } from '@/routes/tenant/disclosure/policy/draft';
import { write } from '@/routes/tenant/disclosure';
import type { PolicyDraft } from '@/types';

/**
 * What RH does with the policy from the top of the page (board V2-N035-Politica): with a draft, go on with its questions or
 * throw it away; without one, write the next version from the one in force (the first one, in the empty state).
 */
export default function PolicyDraftActions({
    draft,
    hasVersion,
}: {
    draft: PolicyDraft | null;
    hasVersion: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const href = write.url([tenantSlug, 'policy']);

    if (draft === null) {
        return hasVersion ? (
            <Button asChild>
                <Link href={href}>
                    <FilePlus2 aria-hidden />
                    Nueva versión
                </Link>
            </Button>
        ) : null;
    }

    return (
        <>
            <ConfirmDialog
                trigger={<Button variant="outline">Descartar borrador</Button>}
                title="¿Descartar el borrador?"
                description="Se pierde lo que contestaste en él. Las versiones ya publicadas no cambian."
                confirmLabel="Descartar"
                form={destroy.form({ tenant: tenantSlug, document: draft.id })}
            />
            <Button asChild>
                <Link href={href}>
                    <PenLine aria-hidden />
                    Continuar con las preguntas
                </Link>
            </Button>
        </>
    );
}
