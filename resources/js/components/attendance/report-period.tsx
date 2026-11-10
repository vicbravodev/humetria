import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import PillChoice from '@/components/humetria/pill-choice';
import { Label } from '@/components/ui/label';
import type { ReportPreset } from '@/types';

/** The period's two ends, both included, for when it is none of the usual ones. */
export type Period = { from: string; to: string };

/**
 * The period of the report: the usual ones in a row of pills (board A7) and «Otro», which opens the first and last day.
 * Picking a usual one sets both days; the errors of the server go under the day they are about.
 */
export default function ReportPeriod({
    presets,
    mode,
    period,
    errors,
    onMode,
    onPeriod,
}: {
    presets: ReportPreset[];
    mode: ReportPreset['key'] | 'other';
    period: Period;
    errors: Partial<Record<'from' | 'to', string>>;
    onMode: (mode: ReportPreset['key'] | 'other') => void;
    onPeriod: (period: Period) => void;
}) {
    return (
        <div className="flex flex-col gap-3">
            <PillChoice
                name="period"
                legend="Periodo"
                options={[
                    ...presets.map(({ key, label }) => ({ value: key, label })),
                    { value: 'other', label: 'Otro' },
                ]}
                value={mode}
                onChange={onMode}
            />
            {mode === 'other' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="report-from">Desde</Label>
                        <DatePicker
                            id="report-from"
                            name="from"
                            value={period.from}
                            onChange={(from) => onPeriod({ ...period, from })}
                            invalid={errors.from !== undefined}
                        />
                        <InputError message={errors.from} />
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="report-to">Hasta</Label>
                        <DatePicker
                            id="report-to"
                            name="to"
                            value={period.to}
                            min={period.from}
                            onChange={(to) => onPeriod({ ...period, to })}
                            invalid={errors.to !== undefined}
                        />
                        <InputError message={errors.to} />
                    </div>
                </div>
            )}
        </div>
    );
}
