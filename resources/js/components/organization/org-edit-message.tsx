import { CircleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * What stopped a change of manager, said calmly (sand, never red) and in words: why a drop was not possible, or the
 * rule the server answered. It is read out as it appears.
 */
export default function OrgEditMessage({
    message,
    className,
}: {
    message: string;
    className?: string;
}) {
    return (
        <p
            role="alert"
            data-slot="org-edit-message"
            className={cn(
                'flex items-start gap-2 rounded-lg bg-warning px-3 py-2 text-sm text-warning-foreground',
                className,
            )}
        >
            <CircleAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
            {message}
        </p>
    );
}
