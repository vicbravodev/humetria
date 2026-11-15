import { TriangleAlert } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/** The center was put on a map zoomed far out: it may be a slip, so saving waits for RH to say it is where they want it. */
export default function ZoneFarNotice({
    confirmed,
    onConfirm,
}: {
    confirmed: boolean;
    onConfirm: (confirmed: boolean) => void;
}) {
    return (
        <div
            role="status"
            className="grid gap-3 rounded-xl bg-warning p-3 text-sm text-warning-foreground"
        >
            <p className="flex items-start gap-2">
                <TriangleAlert
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0"
                />
                <span>
                    Pusiste el centro con el mapa muy alejado: puede quedar a
                    kilómetros del lugar. Acércate para ubicarlo, o confirma que
                    es aquí.
                </span>
            </p>
            <div className="flex items-center gap-2">
                <Checkbox
                    id="zone-far-confirm"
                    checked={confirmed}
                    onCheckedChange={(value) => onConfirm(value === true)}
                />
                <Label htmlFor="zone-far-confirm">
                    Confirmo que el centro está donde lo quiero
                </Label>
            </div>
        </div>
    );
}
