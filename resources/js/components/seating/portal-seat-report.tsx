import { Form } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/portal/seating/reports';

/** Reports a damaged seat. It does not record who reported: the person only says what is wrong. */
export default function PortalSeatReport({ token }: { token: string }) {
    return (
        <Form
            {...store.form({ token })}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="flex flex-col gap-3 card p-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label
                            htmlFor="report-description"
                            className="text-lg font-semibold"
                        >
                            ¿Un asiento está dañado?
                        </Label>
                        <p className="text-muted-foreground">
                            Cuéntenos cuál y qué le pasa. No guardamos quién
                            avisa: solo llega a la empresa y a la Comisión.
                        </p>
                        <Textarea
                            id="report-description"
                            name="description"
                            rows={3}
                            aria-invalid={
                                errors['description'] === undefined
                                    ? undefined
                                    : true
                            }
                        />
                        <InputError message={errors['description']} />
                    </div>
                    <Button variant="outline" disabled={processing}>
                        {processing && <Spinner />}
                        Avisar del asiento dañado
                    </Button>
                </>
            )}
        </Form>
    );
}
