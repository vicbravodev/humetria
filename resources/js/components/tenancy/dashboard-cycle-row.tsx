import { Badge } from '@/components/ui/badge';
import { instrumentsText } from '@/lib/campaigns';
import { cycleTexts } from '@/lib/dashboard';
import type { CycleRow } from '@/types';

/**
 * A work center in its two-year cycle: a bar from where its span starts to where it ends, filled up to today and
 * marked there, with the dates written under it and its standing in a badge.
 */
export default function DashboardCycleRow({ row }: { row: CycleRow }) {
    const texts = cycleTexts(row);
    const people = row.workers === 1 ? '1 persona' : `${row.workers} personas`;

    return (
        <li className="grid items-center gap-x-6 gap-y-3 border-t px-5 py-[18px] @[40rem]/cycle:grid-cols-[minmax(180px,1.1fr)_minmax(240px,2fr)_auto]">
            <div className="flex min-w-0 flex-col gap-0.5">
                <span className="font-medium">{row.name}</span>
                <span className="text-[13px] leading-[18px] text-muted-foreground">
                    {[people, instrumentsText(row.instruments)]
                        .filter((part) => part !== '')
                        .join(' · ')}
                </span>
            </div>
            <div className="flex flex-col gap-2">
                <div aria-hidden className="relative h-2 rounded-full bg-track">
                    {row.standing !== 'never' && (
                        <>
                            <span
                                className="absolute inset-y-0 left-0 rounded-full bg-chart-2"
                                style={{ width: `${row.elapsed}%` }}
                            />
                            <span
                                className="absolute -top-[5px] h-[18px] w-0.5 rounded-[2px] bg-foreground"
                                style={{
                                    left: `min(${row.elapsed}%, 100% - 2px)`,
                                }}
                            />
                        </>
                    )}
                </div>
                <p className="flex justify-between gap-3 text-[13px] leading-[18px] text-muted-foreground">
                    <span>{texts.from}</span>
                    <span className="text-right">{texts.until}</span>
                </p>
            </div>
            <Badge variant={texts.badge.variant} className="justify-self-start">
                {texts.badge.label}
            </Badge>
        </li>
    );
}
