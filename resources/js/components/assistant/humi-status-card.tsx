import { Link } from '@inertiajs/react';
import HumiCardFrame from '@/components/assistant/humi-card-frame';
import type { StatusState, ToolCard } from '@/lib/assistant/tool-cards';
import { riskLabel } from '@/lib/risk-tones';
import { cn } from '@/lib/utils';

const TONE: Record<StatusState, string> = {
    up_to_date: riskLabel('low'),
    in_progress: riskLabel('medium'),
    pending: riskLabel('high'),
    not_applicable: 'bg-muted text-muted-foreground',
};

/** Where each norm stands: its name, its state in words and color, and the counts behind it. */
export default function HumiStatusCard({
    card,
}: {
    card: Extract<ToolCard, { render: 'status_list' }>;
}) {
    return (
        <HumiCardFrame title={card.title} note={card.note} link={card.link}>
            <ul className="flex flex-col gap-3">
                {card.rows.map((row) => (
                    <li key={row.key} className="flex flex-col gap-0.5">
                        <div className="flex items-center justify-between gap-3">
                            {row.link === null ? (
                                <span className="font-medium">{row.label}</span>
                            ) : (
                                <Link
                                    href={row.link}
                                    className="font-medium hover:underline"
                                >
                                    {row.label}
                                </Link>
                            )}
                            <span
                                className={cn(
                                    'rounded-md px-2 py-[3px] text-caption font-medium whitespace-nowrap',
                                    TONE[row.state],
                                )}
                            >
                                {row.stateLabel}
                            </span>
                        </div>
                        <span className="text-sm text-muted-foreground">
                            {row.summary}
                        </span>
                    </li>
                ))}
            </ul>
        </HumiCardFrame>
    );
}
