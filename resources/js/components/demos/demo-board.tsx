import { useRef, useState } from 'react';
import DemoBoardColumn from '@/components/demos/demo-board-column';
import DemoRequestDetail from '@/components/demos/demo-request-detail';
import { useIsMobile } from '@/hooks/use-mobile';
import { demoListQuery } from '@/lib/demo-requests';
import { index } from '@/routes/demo-requests';
import type { DemoBoardColumn as Column, DemoFilters } from '@/types';

/**
 * The funnel (tablero Demos): the four open stages side by side, a few compact cards each, and the request chosen in
 * a fixed panel at the right with its data and what to do next. The newest of the first stage with any is chosen to
 * begin with; below 1280 px the panel sits under the stages and takes the focus when a card is chosen on a phone.
 */
export default function DemoBoard({
    columns,
    filters,
    canManage,
}: {
    columns: Column[];
    filters: DemoFilters;
    canManage: boolean;
}) {
    const cards = columns.flatMap((column) => column.cards);
    const [chosenId, setChosenId] = useState(cards[0]?.id);
    const chosen = cards.find((card) => card.id === chosenId) ?? cards[0];
    const detail = useRef<HTMLElement>(null);
    const stacked = useIsMobile();
    const choose = (id: string) => {
        setChosenId(id);

        if (stacked) {
            detail.current?.focus();
        }
    };

    return (
        <div className="flex flex-col gap-6 xl:flex-row xl:items-start">
            <div className="grid min-w-0 flex-1 grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] items-start gap-4">
                {columns.map((column) => (
                    <DemoBoardColumn
                        key={column.status}
                        column={column}
                        moreHref={index.url({
                            query: demoListQuery({
                                ...filters,
                                view: 'list',
                                status: column.status,
                            }),
                        })}
                        chosen={{ id: chosen?.id, choose }}
                    />
                ))}
            </div>
            {chosen !== undefined && (
                <DemoRequestDetail
                    ref={detail}
                    request={chosen}
                    canManage={canManage}
                />
            )}
        </div>
    );
}
