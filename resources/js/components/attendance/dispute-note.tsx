import type { OverviewDispute } from '@/types';

/** What the person disputed, in their words, and RH's answer once it was resolved. */
export default function DisputeNote({ dispute }: { dispute: OverviewDispute }) {
    return (
        <div className="flex basis-full flex-col gap-1 text-sm">
            <p className="text-pretty">
                <span className="text-muted-foreground">Objetó: </span>“
                {dispute.reason}”
            </p>
            {dispute.resolution !== null && (
                <p className="text-pretty text-muted-foreground">
                    Resuelta por RH: {dispute.resolution}
                </p>
            )}
        </div>
    );
}
