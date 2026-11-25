import { Badge } from '@/components/ui/badge';
import { badgeText } from '@/lib/attendance/offline-marks';

/** What a row of the marks made without a connection is: waiting for RH, or a delivery that was not accepted. */
export default function OfflineBadge({
    view,
}: {
    view: 'offline' | 'not_accepted';
}) {
    return (
        <Badge variant={view === 'offline' ? 'warning' : 'secondary'}>
            {badgeText(view)}
        </Badge>
    );
}
