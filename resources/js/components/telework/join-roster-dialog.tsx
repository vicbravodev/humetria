import { UserCheck } from 'lucide-react';
import { useState } from 'react';
import FormSheet from '@/components/humetria/form-sheet';
import InputError from '@/components/humetria/input-error';
import RosterFields from '@/components/telework/roster-fields';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { join } from '@/routes/tenant/telework/teleworkers';
import type { TeleworkerSummary } from '@/types';

/** Moves an aspirant into the roster with the data of numeral 5.1; it needs a workplace validated and in force. */
export default function JoinRosterDialog({
    teleworker,
    states,
}: {
    teleworker: TeleworkerSummary;
    states: Record<string, string>;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <FormSheet
            open={open}
            onOpenChange={setOpen}
            title="Pasar al padrón de teletrabajo"
            description="Los datos que pide el numeral 5.1 de la NOM-037."
            form={join.form({ tenant: tenantSlug, teleworker: teleworker.id })}
            submit="Guardar y pasar al padrón"
            trigger={
                <SheetTrigger asChild>
                    <Button disabled={!teleworker.canJoin}>
                        <UserCheck aria-hidden />
                        Pasar al padrón
                    </Button>
                </SheetTrigger>
            }
        >
            {(errors) => (
                <>
                    <RosterFields
                        jobTitle={teleworker.jobTitle}
                        home={teleworker.homeDefault}
                        states={states}
                        errors={errors}
                    />
                    <InputError message={errors['workplaces']} />
                    <InputError message={errors['teleworker']} />
                </>
            )}
        </FormSheet>
    );
}
