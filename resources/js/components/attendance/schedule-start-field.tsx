import TimePicker from '@/components/humetria/time-picker';
import { Label } from '@/components/ui/label';

/**
 * The one time every working day starts: «Entra a las …». Empty when the days differ (each one is set in «Personalizar
 * días») or when there is none, which is fine: a day with no start has no lateness.
 */
export default function ScheduleStartField({
    start,
    differs,
    onChange,
}: {
    start: string;
    differs: boolean;
    onChange: (time: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="schedule-start">Entra a las</Label>
            <TimePicker
                id="schedule-start"
                className="w-36"
                value={start}
                onChange={onChange}
                describedBy="schedule-start-hint"
            />
            <p
                id="schedule-start-hint"
                className="text-sm text-pretty text-muted-foreground"
            >
                {differs
                    ? 'Cada día entra a otra hora: míralas en «Personalizar días». Escribe una aquí para todos.'
                    : 'Opcional. Sin hora de entrada, el día no tiene retardo; solo se muestra, no descuenta tiempo.'}
            </p>
        </div>
    );
}
