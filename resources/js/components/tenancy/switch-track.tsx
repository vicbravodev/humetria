import { cn } from '@/lib/utils';

/** How a switch looks, on or off; whoever holds it gives it its role and its words. */
export default function SwitchTrack({ on }: { on: boolean }) {
    return (
        <span
            aria-hidden
            className={cn(
                'relative inline-flex h-6 w-10 shrink-0 rounded-full transition-colors duration-150',
                on ? 'bg-primary' : 'bg-input',
            )}
        >
            <span
                className={cn(
                    'absolute top-[3px] size-[18px] rounded-full bg-background shadow-sm transition-[left] duration-150',
                    on ? 'left-[19px]' : 'left-[3px]',
                )}
            />
        </span>
    );
}
