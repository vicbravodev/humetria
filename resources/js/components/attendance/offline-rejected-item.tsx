import OfflineBadge from '@/components/attendance/offline-badge';
import Initials from '@/components/humetria/initials';
import { shortMoment } from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import { rejectionText } from '@/lib/attendance/offline-marks';
import type { RejectedOfflineRow } from '@/types';

/** A delivery that was not accepted, with why: read-only (the person asks for a correction if one is needed). */
export default function OfflineRejectedItem({
    row,
}: {
    row: RejectedOfflineRow;
}) {
    return (
        <li className="flex items-start gap-3 px-[18px] py-4">
            <Initials name={row.person.name} />
            <span className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                    <span className="font-semibold">{row.person.name}</span>
                    <OfflineBadge view="not_accepted" />
                </span>
                <span className="text-sm font-medium">
                    {markName(row.type)} · {shortMoment(row.declaredAt)}
                </span>
                <span className="text-sm text-muted-foreground">
                    {rejectionText(row.reason)}
                </span>
                <span className="text-sm text-muted-foreground">
                    Llegó {shortMoment(row.receivedAt)}
                </span>
            </span>
        </li>
    );
}
