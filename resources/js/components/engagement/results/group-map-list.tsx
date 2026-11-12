import { Lock } from 'lucide-react';
import GroupMapFigure from '@/components/engagement/results/group-map-figure';
import type { EngagementDriver, EngagementGroup } from '@/types';

/**
 * The map on a phone: one card per group with its themes stacked, so nothing scrolls sideways. Same figures and same
 * «Protegido» rule as the table.
 */
export default function GroupMapList({
    columns,
    groups,
    hasHidden,
}: {
    columns: EngagementDriver[];
    groups: EngagementGroup[];
    hasHidden: boolean;
}) {
    const scoreOf = (group: EngagementGroup, key: string): number | null =>
        group.drivers.find((driver) => driver.key === key)?.score ?? null;
    const cards: { key: string; name: string; scores: (number | null)[] }[] = [
        {
            key: 'all',
            name: 'Toda la empresa',
            scores: columns.map((column) => column.score),
        },
        ...groups.map((group) => ({
            key: group.key,
            name: group.name,
            scores: columns.map((column) => scoreOf(group, column.key)),
        })),
        ...(hasHidden
            ? [
                  {
                      key: 'hidden',
                      name: 'Protegido',
                      scores: columns.map(() => null),
                  },
              ]
            : []),
    ];

    return (
        <ul data-group-list className="flex flex-col gap-3 sm:hidden">
            {cards.map((card) => (
                <li key={card.key} className="flex flex-col gap-2 card p-3">
                    <span className="inline-flex items-center gap-1.5 font-medium">
                        {card.key === 'hidden' && (
                            <Lock aria-hidden className="size-4" />
                        )}
                        {card.name}
                    </span>
                    <dl className="flex flex-col gap-1.5">
                        {columns.map((column, index) => (
                            <div
                                key={column.key}
                                className="flex items-center justify-between gap-3"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {column.label}
                                </dt>
                                <dd className="w-24">
                                    <GroupMapFigure
                                        score={card.scores[index] ?? null}
                                        label={`${card.name} · ${column.label}`}
                                    />
                                </dd>
                            </div>
                        ))}
                    </dl>
                </li>
            ))}
        </ul>
    );
}
