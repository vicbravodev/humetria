import { useForm } from '@inertiajs/react';
import { Armchair } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import PortalCard from '@/components/telework/portal/portal-card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { describeDate } from '@/lib/calendar-date';
import { respond } from '@/routes/portal/equipment';
import type { PortalEquipment } from '@/types';

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
        <PortalCard
            icon={Armchair}
            title={item.title}
            detail={
                <>
                    {item.kind} · entregado el{' '}
                    {describeDate(item.deliveredOn) ?? item.deliveredOn}
                </>
            }
        >
            <p className="mt-1 text-base">
                Confirme que lo recibió. Si no lo acepta, dígalo y escriba por
                qué. Queda constancia de la fecha, la hora y la dirección IP
                desde la que responde.
            </p>
            {refusing && (
                <div className="mt-2 grid gap-2">
                    <Label htmlFor={`reason-${item.id}`}>
                        Motivo por el que no lo acepta
                    </Label>
                    <Textarea
                        id={`reason-${item.id}`}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        aria-invalid={
                            form.errors.reason === undefined ? undefined : true
                        }
                        aria-describedby={`reason-${item.id}-error`}
                    />
                    <InputError
                        id={`reason-${item.id}-error`}
                        message={form.errors.reason}
                    />
                </div>
            )}
            <InputError message={form.errors.equipment} />
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
        </PortalCard>
    );
}
