import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ReportPeriod from '@/components/attendance/report-period';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery } from '@/lib/attendance/report';
import { matchPreset } from '@/lib/attendance/report-person';
import { weekTitle } from '@/lib/attendance/week-text';
import { serverSource } from '@/lib/server-options';
import { report } from '@/routes/tenant/attendance';
import { options } from '@/routes/tenant/work-centers';
import type { AttendanceReportProps } from '@/types';

/** What the report is about: one center, searched on the server, and the period, both its first and last day included. */
export default function ReportFilters({
    center,
    period,
    presets,
    limits,
}: Pick<AttendanceReportProps, 'center' | 'period' | 'presets' | 'limits'>) {
    const tenantSlug = useTenantSlug();
    const errors = usePage().props.errors;
    const [chosen, setChosen] = useState(center?.id ?? '');
    const [days, setDays] = useState(period);
    const [mode, setMode] = useState(
        matchPreset(presets, period.from, period.to),
    );
    const pick = (next: typeof mode) => {
        const preset = presets.find((one) => one.key === next);
        setMode(next);
        setDays(preset ?? days);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            report.url(tenantSlug),
            reportQuery({ center: chosen, ...days, q: '' }),
        );
    };

    return (
        <form
            onSubmit={submit}
            aria-label="Centro y periodo del reporte"
            className="flex flex-col gap-4 card p-5 print:hidden"
        >
            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
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
                <Button type="submit" disabled={chosen === ''}>
                    Ver reporte
                </Button>
            </div>
            <ReportPeriod
                presets={presets}
                mode={mode}
                period={days}
                errors={errors}
                onMode={pick}
                onPeriod={setDays}
            />
            <p className="text-sm text-muted-foreground">
                {weekTitle(days.from, days.to)} · En pantalla hasta{' '}
                {limits.screenDays} días; en CSV, hasta {limits.exportDays}.
            </p>
        </form>
    );
}
