import DateRangePicker from '@/components/humetria/date-range-picker';
import InputError from '@/components/humetria/input-error';
import PillChoice from '@/components/humetria/pill-choice';
import type { ReportPreset } from '@/types';

/** The period's two ends, both included, for when it is none of the usual ones. */
export type Period = { from: string; to: string };

/**
 * The period of the report: the usual ones in a row of pills (board A7) and «Otro», which opens one field for the first and last day.
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
                <div className="grid gap-2">
                    <DateRangePicker
                        id="report"
                        label="Días del periodo"
                        names={{ from: 'from', to: 'to' }}
                        value={period}
                        onChange={onPeriod}
                    />
                    <InputError message={errors.from} />
                    <InputError message={errors.to} />
                </div>
            )}
        </div>
    );
}
