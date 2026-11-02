import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/** A field's error, announced as soon as it appears. */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string | undefined }) {
    return message ? (
        <p
            role="alert"
            {...props}
            className={cn('text-sm text-destructive', className)}
        >
            {message}
        </p>
    ) : null;
}
