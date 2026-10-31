import { Form } from '@inertiajs/react';
import { FileCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import AckPaperFields from '@/components/telework/ack-paper-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/tenant/telework/documents/acknowledgements';

/** Records, for the people ticked in the list, the confirmations they signed on paper: the day and a note about the sheet. */
export default function AckPaperDialog({
    tenantSlug,
    documentId,
    chosen,
    onDone,
}: {
    tenantSlug: string;
    documentId: string;
    chosen: string[];
    onDone: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={chosen.length === 0}>
                    <FileCheck aria-hidden />
                    Registrar acuses en papel ({chosen.length})
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Registrar acuses en papel</DialogTitle>
                <DialogDescription>
                    Para{' '}
                    {chosen.length === 1
                        ? 'la persona elegida'
                        : `las ${chosen.length} personas elegidas`}
                    . Queda constancia del día que firmaron y de tu nota.
                </DialogDescription>
                <Form
                    {...store.form({
                        tenant: tenantSlug,
                        document: documentId,
                    })}
                    noValidate
                    onSuccess={() => {
                        setOpen(false);
                        onDone();
                    }}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            {chosen.map((id) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name="employee_ids[]"
                                    value={id}
                                />
                            ))}
                            <InputError
                                role="alert"
                                message={errors['employee_ids']}
                            />
                            <AckPaperFields errors={errors} />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-fit"
                            >
                                {processing && <Spinner />}
                                Registrar
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
