import { countOf } from '@/lib/plural';
import type { LeavePolicyCard } from '@/types';

/** The policies no longer offered, folded in one line: they take no card. */
export default function ArchivedPolicies({
    policies,
}: {
    policies: LeavePolicyCard[];
}) {
    if (policies.length === 0) {
        return null;
    }

    return (
        <details className="text-meta text-muted-foreground">
            <summary className="cursor-pointer py-2">
                {countOf(
                    policies.length,
                    'política archivada',
                    'políticas archivadas',
                )}
                : {policies.map((policy) => policy.name).join(', ')}
            </summary>
            <p>
                Ya no se ofrecen; los periodos otorgados conservan sus términos.
            </p>
        </details>
    );
}
