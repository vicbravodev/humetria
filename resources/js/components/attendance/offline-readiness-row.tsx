import { CircleAlert, CircleCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type {
    ReadinessFix,
    ReadinessRow,
} from '@/lib/attendance/offline-readiness';

const FIXES: Partial<Record<ReadinessFix, string>> = {
    camera: 'Permitir cámara',
    location: 'Permitir ubicación',
};

/**
 * One thing the phone needs to mark without internet: done (a check) or missing (an alert, in the tone that says whether it
 * blocks or only warns). A permission has its button; to prepare the phone, a link to where it is done; what has to wait for
 * a network says so in its own words.
 */
export default function OfflineReadinessRow({
    row,
    prepareUrl,
    onFix,
}: {
    row: ReadinessRow;
    /** Where this phone is prepared (the person's account), when this page knows it. */
    prepareUrl: string | null;
    onFix: (fix: ReadinessFix) => void;
}) {
    const Icon = row.ok ? CircleCheck : CircleAlert;
    const button = row.fix === null ? undefined : FIXES[row.fix];

    return (
        <li className="flex flex-col gap-2 card p-4">
            <p className="flex items-start gap-3 text-base">
                <Icon
                    aria-hidden
                    className={
                        row.ok
                            ? 'mt-0.5 size-5 shrink-0 text-primary'
                            : 'mt-0.5 size-5 shrink-0 text-warning-foreground'
                    }
                />
                <span>{row.text}</span>
            </p>
            {row.fix !== null && button !== undefined && (
                <Button
                    variant="outline"
                    className="h-12 self-start text-base"
                    onClick={() => row.fix !== null && onFix(row.fix)}
                >
                    {button}
                </Button>
            )}
            {row.fix === 'prepare-phone' && prepareUrl !== null && (
                <Button
                    asChild
                    variant="outline"
                    className="h-12 self-start text-base"
                >
                    <a href={prepareUrl}>Preparar este teléfono</a>
                </Button>
            )}
        </li>
    );
}
