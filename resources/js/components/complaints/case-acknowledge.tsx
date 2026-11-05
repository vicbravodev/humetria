import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { acknowledge } from '@/routes/tenant/complaints/cases';

/** The acknowledgement, already written with the day of the answer (board V4-Quejas-Caso): the handler adjusts it and sends it. */
export default function CaseAcknowledge({
    caseId,
    draft,
}: {
    caseId: string;
    draft: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...acknowledge.form({ tenant: tenantSlug, complaint: caseId })}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <Label
                        htmlFor="case-acknowledgement"
                        className="font-medium"
                    >
                        Acuse de recibo
                    </Label>
                    <Textarea
                        id="case-acknowledgement"
                        name="body"
                        rows={4}
                        defaultValue={draft}
                        className="bg-card"
                        aria-invalid={
                            errors['body'] === undefined ? undefined : true
                        }
                    />
                    <InputError message={errors['body']} />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Ya está redactado. Puedes ajustarlo antes de
                            enviarlo.
                        </p>
                        <Button disabled={processing}>
                            {processing ? <Spinner /> : <Send aria-hidden />}
                            Enviar acuse de recibo
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
