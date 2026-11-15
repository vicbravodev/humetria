import { Clock } from 'lucide-react';
import FormField from '@/components/form-field';
import SettingRow from '@/components/humetria/setting-row';

/** Minutes after a start time that still count as on time (0 to 120): the lateness is shown, never sanctioned. */
export default function PolicyLateTolerance({
    value,
    onChange,
    error,
}: {
    value: number;
    onChange: (minutes: number) => void;
    error: string | undefined;
}) {
    return (
        <SettingRow
            icon={Clock}
            title="Tolerancia de retardo"
            description="Si el horario de una persona tiene hora de entrada, su semana avisa cuando marca después de esta tolerancia."
        >
            <div className="sm:max-w-80">
                <FormField
                    name="late_tolerance_minutes"
                    label="Minutos de tolerancia (0 a 120)"
                    idPrefix="policy"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={120}
                    value={value}
                    onChange={(event) => onChange(Number(event.target.value))}
                    error={error}
                />
                <p className="mt-2 text-sm text-muted-foreground">
                    Solo se muestra: Humetria no descuenta tiempo ni marca
                    faltas.
                </p>
            </div>
        </SettingRow>
    );
}
