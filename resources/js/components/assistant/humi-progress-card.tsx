import { Link } from '@inertiajs/react';
import HumiCardFrame from '@/components/assistant/humi-card-frame';
import ProgressBar from '@/components/humetria/progress-bar';
import type { ToolCard } from '@/lib/assistant/tool-cards';

/** How the campaigns go: for each guide, how many of the people given it answered, and when it closes. */
export default function HumiProgressCard({
    card,
}: {
    card: Extract<ToolCard, { render: 'progress' }>;
}) {
    return (
        <HumiCardFrame title={card.title} note={card.note} link={card.link}>
            <ul className="flex flex-col gap-4">
                {card.rows.map((row) => (
                    <li
                        key={row.link ?? row.label}
                        className="flex flex-col gap-2"
                    >
                        <div className="flex items-baseline justify-between gap-3 text-sm">
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
                            {row.closesOn !== null && (
                                <span className="text-muted-foreground">
                                    Cierra el {row.closesOn}
                                </span>
                            )}
                        </div>
                        {row.guides.map((guide) => (
                            <div
                                key={guide.label}
                                className="flex flex-col gap-1"
                            >
                                <ProgressBar
                                    value={guide.answered}
                                    max={guide.invited}
                                    label={`${guide.label}: ${guide.answered} de ${guide.invited}`}
                                />
                                <span className="text-sm text-muted-foreground">
                                    {guide.label}: {guide.answered} de{' '}
                                    {guide.invited} personas respondieron
                                </span>
                            </div>
                        ))}
                    </li>
                ))}
            </ul>
        </HumiCardFrame>
    );
}
