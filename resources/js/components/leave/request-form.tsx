import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import RequestDates from '@/components/leave/request-dates';
import RequestPreview from '@/components/leave/request-preview';
import RequestTypeField from '@/components/leave/request-type-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SheetClose } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useLeavePreview } from '@/hooks/use-leave-preview';
import { previewQuery } from '@/lib/leave/preview';
import { preview } from '@/routes/portal/vacations';
import { store } from '@/routes/portal/vacations/requests';
import type { LeaveTypeOption } from '@/types';

/**
 * The person asks for vacations or a leave, in the sheet «Pedir días»: as soon as both days are picked it says how many
 * working days they are and how many days would be left. It never sends by itself, its button is never off (without the
 * dates, the server says what is missing) and having no days does not stop the request.
 */
export default function RequestForm({
    slug,
    today,
    types,
    available,
}: {
    slug: string;
    today: string;
    types: LeaveTypeOption[];
    available: number;
}) {
    const [typeId, setTypeId] = useState('');
    const [startsOn, setStartsOn] = useState('');
    const [endsOn, setEndsOn] = useState('');
    const query = previewQuery(startsOn, endsOn, typeId);
    const answer = useLeavePreview(
        query === null ? null : preview.url(slug, { query }),
    );

    return (
        <Form
            {...store.form(slug)}
            options={{ preserveScroll: true }}
            className="flex flex-col gap-5 px-4 pb-6"
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
                    <RequestPreview answer={answer} available={available} />
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
                    <div className="flex flex-col gap-2.5">
                        <Button
                            disabled={processing}
                            className="h-14 w-full text-lg font-semibold"
                        >
                            {processing && <Spinner />}
                            Enviar solicitud
                        </Button>
                        <SheetClose asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-12 w-full text-lg"
                            >
                                Cancelar
                            </Button>
                        </SheetClose>
                    </div>
                </>
            )}
        </Form>
    );
}
