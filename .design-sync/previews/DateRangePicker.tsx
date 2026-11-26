import { DateRangePicker, InputError } from 'humetria';
import { useState } from 'react';

const Period = ({ initial, error }: { initial: { from: string; to: string }; error?: string }) => {
    const [period, setPeriod] = useState(initial);

    return (
        <div className="grid gap-2">
            <DateRangePicker
                id="report"
                label="Días del periodo"
                names={{ from: 'from', to: 'to' }}
                value={period}
                onChange={setPeriod}
                shortcuts={[
                    { label: 'Esta semana', from: '2026-11-23', to: '2026-11-29' },
                    { label: 'Este mes', from: '2026-11-01', to: '2026-11-30' },
                ]}
            />
            <InputError message={error} />
        </div>
    );
};

export const AttendanceReport = () => <Period initial={{ from: '2026-11-01', to: '2026-11-15' }} />;

export const Empty = () => <Period initial={{ from: '', to: '' }} />;

export const WithError = () => (
    <Period
        initial={{ from: '2026-11-15', to: '2026-11-01' }}
        error="El último día debe ser después del primero."
    />
);
