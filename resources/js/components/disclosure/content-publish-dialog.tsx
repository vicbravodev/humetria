import CenterPicker from '@/components/disclosure/center-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { publish } from '@/routes/tenant/disclosure/contents';

/**
 * Publishes the draft of a block of 5.7 as its next version, for the centers chosen. Nobody is asked to confirm it: it is told
 * by being in front of the people, in the page or in a means RH records (criterio C-52).
 */
export default function ContentPublishDialog({
    open,
    onOpenChange,
    kind,
    documentId,
    label,
    centersInUse,
    back = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kind: string;
    documentId: string;
    label: string;
    centersInUse: number;
    /** «board» goes back to what the people must know once published (board V4-Difusion-Contenidos). */
    back?: 'board' | null;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Publicar: ${label}`}
            description="Se guarda como la siguiente versión y ya no se puede cambiar: es evidencia de lo que el centro informó."
            form={publish.form(
                { tenant: tenantSlug, kind, document: documentId },
                back === null ? {} : { query: { back } },
            )}
            submit="Publicar"
        >
            {(errors) => (
                <>
                    <CenterPicker
                        inUse={centersInUse}
                        error={
                            errors['work_center_ids'] ??
                            errors['work_center_ids.0']
                        }
                    />
                    <InputError message={errors['body']} />
                </>
            )}
        </FormDialog>
    );
}
