import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { acknowledge } from '@/routes/portal/policy';

/** The person's confirmation, stuck to the foot of the screen while it is pending (criterio C-53). */
export default function PortalConfirm({
    slug,
    documentId,
}: {
    slug: string;
    documentId: string;
}) {
    return (
        <Form
            {...acknowledge.form({ tenant: slug })}
            transform={() => ({ document: documentId })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-2.5"
        >
            {({ processing, errors }) => (
                <>
                    {errors['document'] !== undefined && (
                        <p role="alert" className="text-base text-destructive">
                            {errors['document']}
                        </p>
                    )}
                    <Button
                        disabled={processing}
                        className="h-14 w-full text-lg font-semibold"
                    >
                        {processing && <Spinner />}
                        Confirmar que la leí
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                        Queda registrado que la recibió y la leyó. No tiene que
                        firmar nada.
                    </p>
                </>
            )}
        </Form>
    );
}
