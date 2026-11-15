import { Badge } from '@/components/ui/badge';
import { statementStateVariant } from '@/lib/billing-statements';
import type { StatementState } from '@/types';

/** How a statement stands: a draft, or an issued one by how much of it is paid. */
export default function StatementStateBadge({
    state,
    label,
}: {
    state: StatementState;
    label: string;
}) {
    return <Badge variant={statementStateVariant(state)}>{label}</Badge>;
}
