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
import type { AckAllMatching } from '@/types';

/** Records, for the people ticked in the list, the confirmations they signed on paper: the day and a note about the sheet. */
export default function AckPaperDialog({
    tenantSlug,
    documentId,
    chosen,
    all,
    days,
    onDone,
}: {
    tenantSlug: string;
    documentId: string;
    chosen: string[];
    /** Everyone pending the search found: sent as a request, never as ids, so there is no cap. */
    all: AckAllMatching | null;
    /** The days the paper can carry: from publication to today, both in the display timezone. */
    days: { first: string; today: string };
    onDone: () => void;
}) {
    const [open, setOpen] = useState(false);
    const count = all?.count ?? chosen.length;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button disabled={count === 0}>
                    <FileCheck aria-hidden />
                    Registrar acuses en papel ({count})
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Registrar acuses en papel</DialogTitle>
                <DialogDescription>
                    Para{' '}
                    {count === 1
                        ? 'la persona elegida'
                        : `las ${count} personas elegidas`}
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
                            {all !== null && (
                                <>
                                    <input
                                        type="hidden"
                                        name="all_matching"
                                        value="1"
                                    />
                                    <input
                                        type="hidden"
                                        name="q"
                                        value={all.q}
                                    />
                                </>
                            )}
                            {(all === null ? chosen : []).map((id) => (
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
                            <AckPaperFields days={days} errors={errors} />
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
