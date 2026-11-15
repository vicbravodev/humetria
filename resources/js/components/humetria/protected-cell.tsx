import { Lock } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A figure the privacy minimum keeps back: a lock and «Protegido», the same whatever the reason (a group under the
 * minimum or one that would give another away), never a number nor how many are missing. The word is there as well
 * as the icon.
 */
export default function ProtectedCell({ className }: { className?: string }) {
    return (
        <span
            data-slot="protected-cell"
            className={cn(
                'inline-flex items-center gap-1.5 text-sm text-muted-foreground',
                className,
            )}
        >
            <Lock aria-hidden className="size-4" />
            Protegido
        </span>
    );
}
