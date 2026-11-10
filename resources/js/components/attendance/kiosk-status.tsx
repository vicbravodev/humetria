import { cn } from '@/lib/utils';

/** Whether the screen reaches the server: a code on the wall that no longer turns must say so. */
export default function KioskStatus({ online }: { online: boolean }) {
    return (
        <p
            role="status"
            className={cn(
                'flex items-center gap-2 text-base',
                online
                    ? 'text-muted-foreground'
                    : 'h-[26px] rounded-full bg-risk-alto-bg px-3 text-caption font-medium text-risk-alto-fg',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-2.5 rounded-full',
                    online ? 'bg-primary' : 'animate-pulse bg-current',
                )}
            />
            {online ? 'Conectada' : 'Sin conexión'}
        </p>
    );
}
