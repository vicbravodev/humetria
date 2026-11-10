import ScheduleFields from '@/components/engagement/pulse/schedule-fields';
import FormDialog from '@/components/humetria/form-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/pulse/schedule';
import type { PulseModeOption, PulseSchedule } from '@/types';

/** Sets how often the pulse goes out by itself, and what the next one looks at. Nothing is sent when it is saved. */
export default function ScheduleDialog({
    open,
    onOpenChange,
    schedule,
    frequencies,
    modes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    schedule: PulseSchedule | null;
    frequencies: PulseModeOption[];
    modes: PulseModeOption[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={
                schedule === null
                    ? 'Programar el pulso'
                    : 'Cambiar la programación'
            }
            description="Unos días antes te avisamos por correo con los compromisos que sigan abiertos, para que lo revises antes de que salga."
            form={update.form.put(tenantSlug)}
            submit="Guardar"
        >
            {(errors) => (
                <>
                    <input
                        type="hidden"
                        name="active"
                        value={schedule?.active === false ? '0' : '1'}
                    />
                    <ScheduleFields
                        schedule={schedule}
                        frequencies={frequencies}
                        modes={modes}
                        errors={errors}
                    />
                </>
            )}
        </FormDialog>
    );
}
