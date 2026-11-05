import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * Above the cases (boards V4-Quejas-Bandeja and V4-Quejas-Caso): who may see them, and that opening one leaves a record. The
 * page of a case adds at the right that this reading is already in the log.
 */
export default function ConfidentialBanner({
    children,
    note,
}: {
    children: ReactNode;
    note?: string;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3 rounded-[10px] border bg-muted px-4 py-3">
            <Lock aria-hidden className="size-[18px] shrink-0" />
            <p className="min-w-0 flex-[1_1_300px] text-sm">{children}</p>
            {note !== undefined && (
                <p className="text-sm text-muted-foreground">{note}</p>
            )}
        </div>
    );
}
