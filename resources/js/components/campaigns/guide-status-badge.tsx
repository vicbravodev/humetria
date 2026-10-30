import { Badge } from '@/components/ui/badge';
import type { ParticipantGuide } from '@/types';

const VARIANTS: Record<
    ParticipantGuide['status'],
    'default' | 'secondary' | 'outline'
> = {
    completed: 'default',
    pending: 'secondary',
    cancelled: 'outline',
};

/** "Guía II · Contestó": the guide and where the person stands with it, in words. */
export default function GuideStatusBadge({
    guide,
}: {
    guide: ParticipantGuide;
}) {
    return (
        <Badge variant={VARIANTS[guide.status]}>
            {guide.title} · {guide.statusLabel}
        </Badge>
    );
}
