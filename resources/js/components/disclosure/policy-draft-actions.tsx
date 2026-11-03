import { useForm } from '@inertiajs/react';
import { FilePlus2, Send } from 'lucide-react';
import { useState } from 'react';
import PublishDialog from '@/components/disclosure/publish-dialog';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy, store } from '@/routes/tenant/disclosure/policy/draft';
import type { PolicyDraft } from '@/types';

/**
 * What RH does with the policy from the top of the page: with a draft, publish it or throw it away; without one, start a new
 * version from the one in force (the first one starts from the template, in the empty state).
 */
export default function PolicyDraftActions({
    draft,
    hasVersion,
    centersInUse,
}: {
    draft: PolicyDraft | null;
    hasVersion: boolean;
    centersInUse: number;
}) {
    const tenantSlug = useTenantSlug();
    const [publishing, setPublishing] = useState(false);
    const start = useForm({});

    if (draft === null) {
        return hasVersion ? (
            <Button
                disabled={start.processing}
                onClick={() => start.post(store.url(tenantSlug))}
            >
                {start.processing ? <Spinner /> : <FilePlus2 aria-hidden />}
                Nueva versión desde la vigente
            </Button>
        ) : null;
    }

    return (
        <>
            <ConfirmDialog
                trigger={<Button variant="outline">Descartar borrador</Button>}
                title="¿Descartar el borrador?"
                description="Se pierde lo que escribiste en él. Las versiones ya publicadas no cambian."
                confirmLabel="Descartar"
                form={destroy.form({ tenant: tenantSlug, document: draft.id })}
            />
            <Button onClick={() => setPublishing(true)}>
                <Send aria-hidden />
                Publicar
            </Button>
            {publishing && (
                <PublishDialog
                    open={publishing}
                    onOpenChange={setPublishing}
                    documentId={draft.id}
                    centersInUse={centersInUse}
                />
            )}
        </>
    );
}
