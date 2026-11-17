import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * «Trabaja a distancia»: the exception of someone who works away from a center where the rest mark on site. They mark
 * with no zone and no position kept. A center «En campo» already marks that way without this.
 */
export default function ScheduleRemoteField({
    remote,
    onChange,
}: {
    remote: boolean;
    onChange: (remote: boolean) => void;
}) {
    return (
        <div className="flex items-start gap-3 rounded-lg border bg-card p-4">
            <Checkbox
                id="schedule-remote"
                checked={remote}
                onCheckedChange={(next) => onChange(next === true)}
                className="mt-0.5"
            />
            <div className="grid gap-1">
                <Label htmlFor="schedule-remote">Trabaja a distancia</Label>
                <p className="text-sm text-pretty text-muted-foreground">
                    Marca desde donde esté, sin zona de asistencia y sin guardar
                    su ubicación. Es solo para quien trabaja a distancia en un
                    centro presencial: en un centro «En campo» todas las
                    personas ya marcan en campo sin esto.
                </p>
            </div>
        </div>
    );
}
