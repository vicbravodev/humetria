import { Building2 } from 'lucide-react';
import KioskStatus from '@/components/attendance/kiosk-status';
import { clockNow } from '@/lib/attendance/correction';
import { dayTitle } from '@/lib/attendance/week-text';

/** The top of the screen: whose center it is, and the company's clock; a chip says so when the connection is lost. */
export default function KioskHeader({
    company,
    center,
    online,
    now,
    timezone,
}: {
    company: string;
    center: string;
    online: boolean;
    now: number;
    timezone: string;
}) {
    const { date, time } = clockNow(new Date(now), timezone);

    return (
        <header className="flex w-full flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-3">
                <span
                    aria-hidden
                    className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <Building2 className="size-6" />
                </span>
                <div className="flex flex-col">
                    <p className="text-xl font-semibold">{company}</p>
                    <p className="text-lg text-muted-foreground">{center}</p>
                </div>
            </div>
            <div className="flex items-center gap-6">
                {!online && <KioskStatus online={false} />}
                <div className="flex flex-col items-end">
                    <p className="text-4xl font-semibold tabular-nums">
                        {time}
                    </p>
                    <p className="text-lg text-muted-foreground">
                        {dayTitle(date)}
                    </p>
                </div>
            </div>
        </header>
    );
}
