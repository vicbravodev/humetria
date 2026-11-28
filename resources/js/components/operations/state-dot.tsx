import { cn } from '@/lib/utils';
import type { ServiceState } from '@/types';

const TONES: Record<ServiceState['variant'], string> = {
    success: 'bg-chart-2',
    warning: 'bg-warning-foreground',
    destructive: 'bg-destructive',
    secondary: 'bg-muted-foreground',
};

/** The dot before a service or an alert: its tone only, the words beside it name the state. */
export default function StateDot({
    variant,
}: {
    variant: ServiceState['variant'];
}) {
    return (
        <span
            aria-hidden
            className={cn('size-2 shrink-0 rounded-full', TONES[variant])}
        />
    );
}
