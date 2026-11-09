import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery } from '@/lib/attendance/report';
import { serverSource } from '@/lib/server-options';
import { report } from '@/routes/tenant/attendance';
import { options } from '@/routes/tenant/work-centers';
import type { AttendanceReportProps } from '@/types';

/** What the report is about: one center, searched on the server, and the period's first and last day, both included. */
export default function ReportFilters({
    center,
    period,
}: Pick<AttendanceReportProps, 'center' | 'period'>) {
    const tenantSlug = useTenantSlug();
    const errors = usePage().props.errors;
    const [chosen, setChosen] = useState(center?.id ?? '');
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            report.url(tenantSlug),
            reportQuery({ center: chosen, from, to, q: '' }),
        );
    };

    return (
        <form
            onSubmit={submit}
            aria-label="Centro y periodo del reporte"
            className="grid gap-4 card p-5 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
        >
            <div className="flex flex-col gap-2">
                <Label htmlFor="report-center">Centro de trabajo</Label>
                <Combobox
                    id="report-center"
                    name="center"
                    source={serverSource(options.url(tenantSlug))}
                    defaultValue={
                        center === null
                            ? undefined
                            : { value: center.id, label: center.name }
                    }
                    placeholder="Escribe para buscar un centro"
                    invalid={errors['center'] !== undefined}
                    onChange={(option) => setChosen(option.value)}
                />
                <InputError message={errors['center']} />
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor="report-from">Desde</Label>
                <DatePicker
                    id="report-from"
                    name="from"
                    value={from}
                    onChange={setFrom}
                    invalid={errors['from'] !== undefined}
                />
                <InputError message={errors['from']} />
            </div>
            <div className="flex flex-col gap-2">
                <Label htmlFor="report-to">Hasta</Label>
                <DatePicker
                    id="report-to"
                    name="to"
                    value={to}
                    min={from}
                    onChange={setTo}
                    invalid={errors['to'] !== undefined}
                />
                <InputError message={errors['to']} />
            </div>
            <Button type="submit" disabled={chosen === ''}>
                Ver reporte
            </Button>
        </form>
    );
}
