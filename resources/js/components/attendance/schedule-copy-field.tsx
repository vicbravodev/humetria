import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { scheduleCopies } from '@/lib/attendance/schedule-copy';
import { options } from '@/routes/tenant/attendance/schedules';
import type { AgreedWeek } from '@/types';

/** «Igual que …»: someone who already has a schedule, searched on the server, whose week fills this one. */
export default function ScheduleCopyField({
    onCopy,
}: {
    onCopy: (schedule: AgreedWeek) => void;
}) {
    const tenantSlug = useTenantSlug();
    const [copies] = useState(() => scheduleCopies(options.url(tenantSlug)));

    return (
        <div className="grid gap-2">
            <Label htmlFor="schedule-copy">O igual que otra persona</Label>
            <Combobox
                id="schedule-copy"
                name="copy_from"
                source={copies.source}
                placeholder="Busca a alguien que ya tenga horario"
                onChange={(option) => {
                    const schedule = copies.scheduleOf(option.value);

                    if (schedule !== null) {
                        onCopy(schedule);
                    }
                }}
            />
        </div>
    );
}
