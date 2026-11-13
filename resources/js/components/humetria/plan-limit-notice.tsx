import { CircleAlert } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { expansionMailto, planUsage } from '@/lib/limits';
import type { PlanLimit } from '@/types';

/**
 * How much of the plan a company uses (people, work centers), under the title of the page that adds them. Quiet while there is
 * room; a notice at the limit, with what to do. Nothing for a company without a plan.
 */
export default function PlanLimitNotice({
    limit,
    noun,
    what,
}: {
    limit: PlanLimit | null;
    noun: { singular: string; plural: string };
    /** What is short, for the subject of the mail that asks for the expansion. */
    what: 'centros' | 'personas';
}) {
    const { support, tenant } = usePage().props;

    if (limit === null) {
        return null;
    }

    const { text, full } = planUsage(limit, noun);
    const mailto = expansionMailto(
        support.email,
        what,
        tenant?.trade_name ?? '',
    );

    return full ? (
        <div
            role="status"
            data-slot="notice"
            className="flex items-center gap-x-4 rounded-xl bg-warning px-5 py-3.5 text-warning-foreground"
        >
            <CircleAlert aria-hidden className="size-5 shrink-0" />
            <p className="min-w-0 flex-1 text-meta">
                {text}
                {mailto !== null && (
                    <>
                        {' '}
                        <a
                            href={mailto}
                            className="font-medium underline underline-offset-4"
                        >
                            Escribir a Humetría
                        </a>
                    </>
                )}
            </p>
        </div>
    ) : (
        <p data-slot="plan-usage" className="text-meta text-muted-foreground">
            {text}
        </p>
    );
}
