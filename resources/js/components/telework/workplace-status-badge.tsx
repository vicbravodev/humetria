import { Badge } from '@/components/ui/badge';
import { statusTone } from '@/lib/telework/review';
import type { WorkplaceStatusValue } from '@/types';

/** Where the place stands, always worded by the server (derived, never edited by hand). */
export default function WorkplaceStatusBadge({
    status,
    label,
}: {
    status: WorkplaceStatusValue;
    label: string;
}) {
    return <Badge variant={statusTone(status)}>{label}</Badge>;
}
