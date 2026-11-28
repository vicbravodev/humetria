import { cn } from '@/lib/utils';

/** How a switch looks, on or off, the same as the app's (complaints' legal hold); whoever holds it gives it its role and its words. */
export default function SwitchTrack({ on }: { on: boolean }) {
    return (
        <span
            aria-hidden
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border-2 border-transparent transition-colors duration-150',
                on ? 'bg-primary' : 'bg-input',
            )}
        >
            <span
                className={cn(
                    'inline-block size-5 rounded-full bg-background shadow-raised transition-transform duration-150',
                    on ? 'translate-x-5' : 'translate-x-0',
                )}
            />
        </span>
    );
}
