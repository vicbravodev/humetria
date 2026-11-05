import { Form } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { close } from '@/routes/tenant/complaints/cases';
import type { CaseResolutionOption } from '@/types';

/** Closes the case with the resolution the handler classifies it under; the platform does not conclude whether there was violence. */
export default function CloseCaseDialog({
    caseId,
    resolutions,
}: {
    caseId: string;
    resolutions: CaseResolutionOption[];
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button className="self-end">
                    <CircleCheck aria-hidden />
                    Cerrar con una respuesta
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Cerrar el caso</DialogTitle>
                <DialogDescription>
                    Es tu clasificación, no una conclusión de Humetría. La
                    persona verá la resolución en palabras generales y el
                    contenido se conservará el tiempo que fijó la empresa.
                </DialogDescription>
                <Form
                    {...close.form({ tenant: tenantSlug, complaint: caseId })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="flex flex-col gap-2">
                                <legend className="mb-1 font-medium">
                                    Resolución
                                </legend>
                                {resolutions.map((resolution) => (
                                    <label
                                        key={resolution.value}
                                        className="flex items-center gap-3 rounded-lg border px-4 py-2.5 has-checked:border-primary has-checked:bg-accent"
                                    >
                                        <input
                                            type="radio"
                                            name="resolution"
                                            value={resolution.value}
                                            className="size-5 accent-primary"
                                        />
                                        {resolution.label}
                                    </label>
                                ))}
                            </fieldset>
                            <InputError message={errors['resolution']} />
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="case-answer">
                                    Lo que le decimos a la persona
                                </Label>
                                <Textarea
                                    id="case-answer"
                                    name="body"
                                    rows={4}
                                    placeholder="Revisamos lo que nos contó y…"
                                    aria-describedby="case-answer-hint"
                                />
                                <p
                                    id="case-answer-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    Lo lee con su referencia y su clave antes de
                                    ver que se cerró. Si lo dejas vacío, solo ve
                                    la resolución.
                                </p>
                                <InputError message={errors['body']} />
                            </div>
                            <Button disabled={processing} className="self-end">
                                {processing && <Spinner />}
                                Cerrar y responder
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
