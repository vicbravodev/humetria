import { useForm } from '@inertiajs/react';
import { Armchair } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { describeDate } from '@/lib/calendar-date';
import { respond } from '@/routes/portal/equipment';
import type { PortalEquipment } from '@/types';

const AREA =
    'min-h-24 w-full rounded-md border border-input bg-card px-4 py-3 text-base focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive';

/** An item the company says it delivered: the person confirms they received it, or refuses it with the reason. */
export default function PortalEquipmentCard({
    item,
    token,
}: {
    item: PortalEquipment;
    token: string;
}) {
    const form = useForm({ reason: '', equipment: '' });
    const [refusing, setRefusing] = useState(false);
    const send = (received: boolean) => {
        form.transform((data) => ({
            received,
            reason: received ? '' : data.reason,
        }));
        form.post(respond.url({ token, item: item.id }));
    };

    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <Armchair aria-hidden className="size-5" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="text-base font-semibold">{item.title}</p>
                <p className="text-base text-muted-foreground">
                    {item.kind} · entregado el{' '}
                    {describeDate(item.deliveredOn) ?? item.deliveredOn}
                </p>
                <p className="mt-1 text-base">
                    Confirme que lo recibió. Si no lo acepta, dígalo y escriba
                    por qué. Queda constancia de la fecha, la hora y la
                    dirección IP desde la que responde.
                </p>
                {refusing && (
                    <div className="mt-2 grid gap-2">
                        <Label htmlFor={`reason-${item.id}`}>
                            Motivo por el que no lo acepta
                        </Label>
                        <textarea
                            id={`reason-${item.id}`}
                            className={AREA}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            aria-invalid={
                                form.errors.reason === undefined
                                    ? undefined
                                    : true
                            }
                            aria-describedby={`reason-${item.id}-error`}
                        />
                        <InputError
                            id={`reason-${item.id}-error`}
                            role="alert"
                            message={form.errors.reason}
                        />
                    </div>
                )}
                <InputError role="alert" message={form.errors.equipment} />
                <div className="mt-2 flex flex-wrap gap-2">
                    {refusing ? (
                        <>
                            <Button
                                className="h-12 text-base"
                                disabled={form.processing}
                                onClick={() => send(false)}
                            >
                                Enviar mi negativa
                            </Button>
                            <Button
                                variant="outline"
                                className="h-12 text-base"
                                onClick={() => setRefusing(false)}
                            >
                                Volver
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                className="h-12 text-base"
                                disabled={form.processing}
                                onClick={() => send(true)}
                            >
                                Lo recibí
                            </Button>
                            <Button
                                variant="outline"
                                className="h-12 text-base"
                                onClick={() => setRefusing(true)}
                            >
                                No lo acepto
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </li>
    );
}
