import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store } from '@/routes/tenant/complaints/cases/messages';

/** «Escribir a la persona»: an answer they read with their reference and key, never anything that identifies them. */
export default function CaseReply({ caseId }: { caseId: string }) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...store.form({ tenant: tenantSlug, complaint: caseId })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <Label htmlFor="case-reply" className="font-medium">
                        Escribir a la persona
                    </Label>
                    <Textarea
                        id="case-reply"
                        name="body"
                        rows={3}
                        className="bg-card"
                        aria-invalid={
                            errors['body'] === undefined ? undefined : true
                        }
                    />
                    <InputError message={errors['body']} />
                    <Button
                        variant="outline"
                        className="self-end"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Enviar a la persona
                    </Button>
                </>
            )}
        </Form>
    );
}
