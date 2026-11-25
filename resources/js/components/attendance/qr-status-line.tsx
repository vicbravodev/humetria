import { Loader2, ScanLine, TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The one line under the window of the reader, on a solid card so it reads over any camera picture: an icon (spinning while
 * the camera opens, a triangle when something needs the person's hand) and what to do or what went wrong.
 */
export default function QrStatusLine({
    text,
    opening,
    problem,
    shown,
}: {
    text: string;
    opening: boolean;
    problem: boolean;
    shown: boolean;
}) {
    const Icon = problem ? TriangleAlert : ScanLine;

    return (
        <p
            role="status"
            className={cn(
                'flex items-center justify-center gap-2 rounded-2xl bg-background px-4 py-3 text-center text-lg',
                shown ? 'shadow-lg' : 'mb-auto',
                problem ? 'text-foreground' : 'text-muted-foreground',
            )}
        >
            {shown &&
                (opening ? (
                    <Loader2
                        aria-hidden
                        className="size-5 shrink-0 animate-spin"
                    />
                ) : (
                    <Icon aria-hidden className="size-5 shrink-0" />
                ))}
            {text}
        </p>
    );
}
