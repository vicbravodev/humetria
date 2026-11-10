import { CircleDot } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { changesText } from '@/lib/attendance/policy-preview';
import { cn } from '@/lib/utils';

/** The end of the policy: how many changes wait (they go to the bitácora), going back to the saved ones, and saving. */
export default function PolicySaveBar({
    changes,
    processing,
    onDiscard,
}: {
    changes: number;
    processing: boolean;
    onDiscard: () => void;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 rounded-xl border px-5 py-3.5',
                changes > 0 ? 'border-transparent bg-warning' : 'bg-card',
            )}
        >
            <p
                role="status"
                className="flex items-center gap-2.5 text-sm font-medium"
            >
                <CircleDot
                    aria-hidden
                    className={cn(
                        'size-4',
                        changes > 0
                            ? 'text-warning-foreground'
                            : 'text-muted-foreground',
                    )}
                />
                {changesText(changes)}
            </p>
            <div className="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={changes === 0 || processing}
                    onClick={onDiscard}
                >
                    Descartar
                </Button>
                <Button disabled={processing}>
                    {processing && <Spinner />}
                    Guardar política
                </Button>
            </div>
        </div>
    );
}
