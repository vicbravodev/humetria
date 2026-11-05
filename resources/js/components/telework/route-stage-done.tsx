import { Link } from '@inertiajs/react';
import { stageHref } from '@/lib/telework/route';
import type { RouteStage } from '@/lib/telework/route';

/** A done stage folded into one line: what was done, and where to see it. */
export default function RouteStageDone({ stage }: { stage: RouteStage }) {
    const href = stageHref(stage);
    const action = stage.rows.find((row) => row.href !== null)?.action ?? 'Ver';

    return (
        <p className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
            <span>{stage.summary}</span>
            {href !== null && (
                <Link
                    href={href}
                    className="touch-hit font-medium text-primary hover:underline"
                >
                    {action}
                </Link>
            )}
        </p>
    );
}
