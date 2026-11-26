import { GlideChips } from 'humetria';
import { House, CalendarDays, Clock, UserRound } from 'lucide-react';

const TABS = [
    { key: 'home', title: 'Inicio', icon: House },
    { key: 'mark', title: 'Marcar', icon: Clock },
    { key: 'leave', title: 'Vacaciones', icon: CalendarDays },
    { key: 'me', title: 'Yo', icon: UserRound },
];

const Bar = ({ current }: { current: string }) => (
    <nav aria-label="Menú del portal" className="flex gap-1 rounded-full border bg-card p-1 shadow-raised max-w-sm">
        {TABS.map(({ key, title, icon: Icon }) => (
            <div key={key} className="relative flex flex-auto">
                <GlideChips group={`portal-${current}`} current={key === current} hovered={false} className="rounded-full" />
                <span
                    className={`relative flex min-h-14 w-full flex-col items-center justify-center gap-0.5 rounded-full px-3 text-caption font-medium ${key === current ? 'text-primary-foreground' : 'text-foreground'}`}
                >
                    <Icon aria-hidden className="size-5" />
                    {title}
                </span>
            </div>
        ))}
    </nav>
);

export const OnMarcar = () => <Bar current="mark" />;

export const OnVacaciones = () => <Bar current="leave" />;
