import { Form } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { acknowledge } from '@/routes/portal/policy';
import type { PolicyAcknowledgment } from '@/types';

/** The person's confirmation: the button while it is pending, the day they confirmed after (criterio C-53). */
export default function PortalConfirm({
    token,
    acknowledgment,
}: {
    token: string;
    acknowledgment: PolicyAcknowledgment;
}) {
    if (acknowledgment.state === 'confirmed') {
        return (
            <p className="flex items-center gap-2 rounded-xl bg-accent px-4 py-3 text-base text-accent-foreground">
                <CircleCheck aria-hidden className="size-5 shrink-0" />
                Usted confirmó el {acknowledgment.on} que leyó esta política.
            </p>
        );
    }

    return (
        <Form
            {...acknowledge.form({ token })}
            transform={() => ({ document: acknowledgment.documentId })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-3 card p-5"
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-base">
                        Cuando termine de leerla, confirme que la leyó. Es solo
                        una constancia: no tiene que firmar nada.
                    </p>
                    {errors['document'] !== undefined && (
                        <p role="alert" className="text-base text-destructive">
                            {errors['document']}
                        </p>
                    )}
                    <Button size="lg" disabled={processing}>
                        {processing && <Spinner />}
                        Confirmo que la leí
                    </Button>
                </>
            )}
        </Form>
    );
}
