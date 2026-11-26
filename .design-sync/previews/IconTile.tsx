import { IconTile } from 'humetria';
import { CalendarDays, FileText, MonitorSmartphone, Route } from 'lucide-react';

export const Icons = () => (
    <div className="flex gap-3">
        <IconTile icon={CalendarDays} />
        <IconTile icon={Route} />
        <IconTile icon={MonitorSmartphone} />
        <IconTile icon={FileText} />
    </div>
);

export const BeforeATitle = () => (
    <div className="flex max-w-md items-start gap-3 card p-4">
        <IconTile icon={FileText} />
        <div className="flex min-w-0 flex-1 flex-col gap-1">
            <p className="text-base font-semibold">Avisar de un accidente</p>
            <p className="text-base text-muted-foreground">
                Cuéntenos la fecha en que ocurrió y su descripción.
            </p>
        </div>
    </div>
);

export const WeekLink = () => (
    <div className="flex max-w-md items-center gap-3 card p-4">
        <IconTile icon={CalendarDays} />
        <p className="flex-1 text-base font-semibold">Mi semana</p>
    </div>
);
