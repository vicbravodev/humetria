import { Form } from '@inertiajs/react';
import { useState } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import ReferralStatusField from '@/components/results/referral-status-field';
import ReportField from '@/components/results/report-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { nextAction } from '@/lib/referral';
import { update } from '@/routes/tenant/campaigns/screenings';
import type { ScreenedPerson } from '@/types';

/** Where HR records the channeling of a person: referred or attended, the day and the institution (num. 5.5, 5.8 c). */
export default function ReferralDialog({
    campaignId,
    person,
}: {
    campaignId: string;
    person: ScreenedPerson;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);
    const action = nextAction(person.referral);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant={action.primary ? 'default' : 'outline'}>
                    {action.label}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>Canalización de {person.name}</DialogTitle>
                <DialogDescription>
                    Deja constancia de que se canalizó o se atendió (numerales
                    5.5 y 5.8). Escribe solo la institución: nada del
                    diagnóstico.
                </DialogDescription>
                <Form
                    {...update.form([tenantSlug, campaignId, person.id])}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    noValidate
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <ReferralStatusField
                                defaultValue={action.status}
                                error={errors['status']}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="referred-on">
                                    Fecha de canalización
                                </Label>
                                <DatePicker
                                    id="referred-on"
                                    name="referred_on"
                                    defaultValue={person.referral?.referredOn}
                                    invalid={
                                        errors['referred_on'] !== undefined
                                    }
                                />
                                <InputError message={errors['referred_on']} />
                            </div>
                            <ReportField
                                name="institution"
                                label="Institución"
                                value={person.referral?.institution ?? ''}
                                error={errors['institution']}
                                hint="Por ejemplo: IMSS, Unidad de Medicina Familiar 7."
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
