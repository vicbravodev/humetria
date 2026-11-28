import { Check, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Whether a role holds a permission: a check and «Sí», or a dash and «No»; never color alone. Compact (the card beside
 * the team), the word is said only to screen readers and the mark is centered.
 */
export default function PermissionHeld({
    held,
    compact = false,
}: {
    held: boolean;
    compact?: boolean;
}) {
    const Icon = held ? Check : Minus;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5',
                !held && 'text-muted-foreground',
                compact && 'w-full justify-center',
            )}
        >
            <Icon
                aria-hidden
                className={cn('size-4', held && 'text-primary')}
            />
            <span className={cn(compact && 'sr-only')}>
                {held ? 'Sí' : 'No'}
            </span>
        </span>
    );
}
