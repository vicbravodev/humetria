import { Form } from '@inertiajs/react';
import { UserCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import RosterFields from '@/components/telework/roster-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { join } from '@/routes/tenant/telework/teleworkers';
import type { TeleworkerSummary } from '@/types';

/** Moves an aspirant into the roster with the data of numeral 5.1; it needs a workplace validated and in force. */
export default function JoinRosterDialog({
    tenantSlug,
    teleworker,
    states,
}: {
    tenantSlug: string;
    teleworker: TeleworkerSummary;
    states: Record<string, string>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={!teleworker.canJoin}>
                    <UserCheck aria-hidden />
                    Pasar al padrón
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Pasar al padrón de teletrabajo</DialogTitle>
                <DialogDescription>
                    Los datos que pide el numeral 5.1 de la NOM-037.
                </DialogDescription>
                <Form
                    {...join.form({
                        tenant: tenantSlug,
                        teleworker: teleworker.id,
                    })}
                    noValidate
                    onSuccess={() => setOpen(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <RosterFields
                                jobTitle={teleworker.jobTitle}
                                states={states}
                                errors={errors}
                            />
                            <InputError
                                role="alert"
                                message={errors['workplaces']}
                            />
                            <InputError
                                role="alert"
                                message={errors['teleworker']}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Guardar y pasar al padrón
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
