import { Link } from '@inertiajs/react';
import HumiCardFrame from '@/components/assistant/humi-card-frame';
import HumiCriterionRow from '@/components/assistant/humi-criterion-row';
import type { ToolCard } from '@/lib/assistant/tool-cards';

/** A list of things the company still has to do, or of criteria of a norm: each with its title, its detail and where to go. */
export default function HumiListCard({
    card,
}: {
    card: Extract<ToolCard, { render: 'list' }>;
}) {
    return (
        <HumiCardFrame title={card.title} note={card.note} link={card.link}>
            <ul className="flex flex-col gap-3">
                {card.rows.map((row) => (
                    <li
                        key={`${row.title}-${row.link ?? ''}`}
                        className="flex flex-col gap-0.5"
                    >
                        {row.ref !== null ? (
                            <HumiCriterionRow row={{ ...row, ref: row.ref }} />
                        ) : row.link === null ? (
                            <span className="font-medium">{row.title}</span>
                        ) : (
                            <Link
                                href={row.link}
                                className="font-medium hover:underline"
                            >
                                {row.title}
                            </Link>
                        )}
                        {row.ref === null && row.detail !== null && (
                            <span className="line-clamp-3 text-sm text-muted-foreground">
                                {row.detail}
                            </span>
                        )}
                        {row.due !== null && (
                            <span className="text-sm text-muted-foreground">
                                Fecha: {row.due}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </HumiCardFrame>
    );
}
