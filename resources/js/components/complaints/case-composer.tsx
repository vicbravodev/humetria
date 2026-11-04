import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/humetria/input-error';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { store as noteStore } from '@/routes/tenant/complaints/cases/notes';
import { store as messageStore } from '@/routes/tenant/complaints/cases/messages';

/** The two things a handler writes: to the person (who reads it with their key) and a note only the handlers read. */
export default function CaseComposer({ caseId }: { caseId: string }) {
    const tenantSlug = useTenantSlug();
    const target = { tenant: tenantSlug, complaint: caseId };
    const forms = [
        {
            id: 'message',
            label: 'Escribir a la persona',
            hint: 'La lee cuando vuelve con su referencia y su clave. No escribas nada que la identifique.',
            route: messageStore,
            action: 'Enviar a la persona',
        },
        {
            id: 'note',
            label: 'Nota interna',
            hint: 'Solo la ven quienes tienen este caso. La persona no la ve.',
            route: noteStore,
            action: 'Guardar nota',
        },
    ];

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {forms.map((form) => (
                <Form
                    key={form.id}
                    {...form.route.form(target)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-3 card p-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Label
                                htmlFor={`case-${form.id}`}
                                className="text-base font-semibold"
                            >
                                {form.label}
                            </Label>
                            <p className="text-caption text-muted-foreground">
                                {form.hint}
                            </p>
                            <Textarea
                                id={`case-${form.id}`}
                                name="body"
                                rows={4}
                                aria-invalid={
                                    errors['body'] === undefined
                                        ? undefined
                                        : true
                                }
                            />
                            <InputError message={errors['body']} />
                            <Button
                                variant="outline"
                                className="self-start"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {form.action}
                            </Button>
                        </>
                    )}
                </Form>
            ))}
        </div>
    );
}
