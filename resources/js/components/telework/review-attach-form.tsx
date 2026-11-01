import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/visit-authorizations/document';

/** Attaches the paper authorization the person signed (PDF), when they did not confirm it in their portal. */
export default function ReviewAttachForm({
    tenantSlug,
    authorizationId,
}: {
    tenantSlug: string;
    authorizationId: string;
}) {
    return (
        <Form
            {...store.form({
                tenant: tenantSlug,
                authorization: authorizationId,
            })}
            className="flex flex-wrap items-center gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        name="document"
                        type="file"
                        accept="application/pdf"
                        aria-label="Autorización firmada en PDF"
                        className="min-w-0 rounded-md border border-input bg-card px-3 py-1.5 text-[14px] file:mr-3 file:font-medium"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        variant="outline"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Adjuntar PDF
                    </Button>
                    <InputError role="alert" message={errors['document']} />
                </>
            )}
        </Form>
    );
}
