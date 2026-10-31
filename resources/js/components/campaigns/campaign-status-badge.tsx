import { Badge } from '@/components/ui/badge';
import type { CampaignStatus } from '@/types';

const VARIANTS: Record<CampaignStatus, 'default' | 'secondary'> = {
    active: 'default',
    draft: 'secondary',
    closed: 'secondary',
};

/** En curso, Borrador or Cerrada: a dot and the word, never the color alone. */
export default function CampaignStatusBadge({
    status,
    label,
}: {
    status: CampaignStatus;
    label: string;
}) {
    return <Badge variant={VARIANTS[status]}>{label}</Badge>;
}
