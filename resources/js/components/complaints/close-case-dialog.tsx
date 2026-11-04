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
import { Spinner } from '@/components/ui/spinner';
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
                <Button>
                    <CircleCheck aria-hidden />
                    Cerrar el caso
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
                            <Button disabled={processing} className="self-end">
                                {processing && <Spinner />}
                                Cerrar el caso
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
