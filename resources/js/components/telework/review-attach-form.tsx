import { Form } from '@inertiajs/react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/telework/visit-authorizations/document';

/** Attaches the paper authorization the person signed (PDF), when they did not confirm it in their portal. */
export default function ReviewAttachForm({
    authorizationId,
}: {
    authorizationId: string;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <Form
            {...store.form({
                tenant: tenantSlug,
                authorization: authorizationId,
            })}
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <FileDropZone
                        name="document"
                        accept="application/pdf"
                        title="Autorización firmada en PDF"
                        hint="Arrástrala aquí o elígela"
                        invalid={errors['document'] !== undefined}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing}
                        className="justify-self-start"
                    >
                        {processing && <Spinner />}
                        Adjuntar PDF
                    </Button>
                    <InputError message={errors['document']} />
                </>
            )}
        </Form>
    );
}
