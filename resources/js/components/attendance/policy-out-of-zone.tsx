import { MapPinOff } from 'lucide-react';
import ChoiceField from '@/components/humetria/choice-field';
import SettingRow from '@/components/humetria/setting-row';
import type { AttendancePolicy } from '@/types';

const OPTIONS: { value: AttendancePolicy['outOfZone']; label: string }[] = [
    { value: 'block', label: 'Bloquear (recomendado)' },
    { value: 'allow_and_flag', label: 'Permitir y señalar' },
];

/** What a mark out of every zone does: blocked by default, or kept and flagged as weak evidence. */
export default function PolicyOutOfZone({
    value,
    onChange,
    error,
}: {
    value: AttendancePolicy['outOfZone'];
    onChange: (value: AttendancePolicy['outOfZone']) => void;
    error: string | undefined;
}) {
    return (
        <SettingRow
            icon={MapPinOff}
            title="Marcas fuera de zona"
            description="Qué pasa cuando alguien marca con su ubicación fuera de todas las zonas de su centro."
        >
            <ChoiceField
                name="out_of_zone"
                legend="Fuera de zona"
                options={OPTIONS}
                value={value}
                onChange={onChange}
                columns={2}
                error={error}
            />
            <ul className="grid gap-1 text-sm text-pretty text-muted-foreground">
                <li>
                    <strong className="font-medium text-foreground">
                        Bloquear:
                    </strong>{' '}
                    nadie puede marcar fuera de su centro.
                </li>
                <li>
                    <strong className="font-medium text-foreground">
                        Permitir y señalar:
                    </strong>{' '}
                    la marca se guarda, señalada, y queda como evidencia débil
                    en el reporte.
                </li>
            </ul>
        </SettingRow>
    );
}
