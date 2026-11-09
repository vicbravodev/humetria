import { cn } from '@/lib/utils';

/** Whether the screen reaches the server: a code on the wall that no longer turns must say so. */
export default function KioskStatus({ online }: { online: boolean }) {
    return (
        <p
            role="status"
            className={cn(
                'flex items-center gap-2 rounded-full border px-4 py-2 text-base font-medium',
                online ? 'text-foreground' : 'border-warning text-foreground',
            )}
        >
            <span
                aria-hidden
                className={cn(
                    'size-3 rounded-full',
                    online ? 'bg-primary' : 'animate-pulse bg-warning',
                )}
            />
            {online ? 'Conectado' : 'Sin conexión, reintentando'}
        </p>
    );
}
