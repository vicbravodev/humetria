import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import RequestDates from '@/components/leave/request-dates';
import RequestPreview from '@/components/leave/request-preview';
import RequestTypeField from '@/components/leave/request-type-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useLeavePreview } from '@/hooks/use-leave-preview';
import { previewQuery } from '@/lib/leave/preview';
import { preview } from '@/routes/portal/vacations';
import { store } from '@/routes/portal/vacations/requests';
import type { LeaveTypeOption } from '@/types';

/**
 * The person asks for vacations or a leave: as soon as both days are picked it says how many working days they are and
 * whether they have them. It never sends by itself, and having no days does not stop the request.
 */
export default function RequestForm({
    token,
    today,
    types,
}: {
    token: string;
    today: string;
    types: LeaveTypeOption[];
}) {
    const [typeId, setTypeId] = useState('');
    const [startsOn, setStartsOn] = useState('');
    const [endsOn, setEndsOn] = useState('');
    const query = previewQuery(startsOn, endsOn, typeId);
    const answer = useLeavePreview(
        query === null ? null : preview.url({ token }, { query }),
    );
    const clear = () => {
        setTypeId('');
        setStartsOn('');
        setEndsOn('');
    };

    return (
        <section aria-labelledby="leave-ask" className="grid gap-3">
            <h2 id="leave-ask" className="text-xl font-semibold">
                Pedir días
            </h2>
            <Form
                {...store.form({ token })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={clear}
                className="flex flex-col gap-5 card p-5"
            >
                {({ processing, errors }) => (
                    <>
                        <RequestTypeField
                            types={types}
                            value={typeId}
                            onChange={setTypeId}
                            error={errors['leave_type_id']}
                        />
                        <RequestDates
                            today={today}
                            first={{
                                value: startsOn,
                                onChange: setStartsOn,
                                error: errors['starts_on'],
                            }}
                            last={{
                                value: endsOn,
                                onChange: setEndsOn,
                                error: errors['ends_on'],
                            }}
                        />
                        <RequestPreview answer={answer} />
                        <div className="grid gap-2">
                            <Label htmlFor="leave-note">Nota (opcional)</Label>
                            <Textarea
                                id="leave-note"
                                name="note"
                                rows={2}
                                maxLength={500}
                            />
                            <p className="text-sm text-muted-foreground">
                                La leerá quien responda su solicitud.
                            </p>
                            <InputError message={errors['note']} />
                        </div>
                        <Button size="lg" disabled={processing}>
                            {processing && <Spinner />}
                            Enviar solicitud
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
