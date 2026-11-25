import { useRef, useState } from 'react';
import DemoBoardColumn from '@/components/demos/demo-board-column';
import DemoRequestDetail from '@/components/demos/demo-request-detail';
import { useIsMobile } from '@/hooks/use-mobile';
import type { DemoBoardColumn as Column } from '@/types';

/**
 * The funnel (pantallas: Consola · Demos): the open stages side by side, a few cards each, and the request chosen in
 * full beside them with what to do next. The newest of the first stage with any is open to begin with; on a narrow
 * screen the request sits below and takes the focus when chosen.
 */
export default function DemoBoard({
    columns,
    canManage,
}: {
    columns: Column[];
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
        <div className="flex flex-col gap-4 xl:flex-row xl:items-start">
            <div className="grid min-w-0 flex-1 grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] items-start gap-4">
                {columns.map((column) => (
                    <DemoBoardColumn
                        key={column.status}
                        column={column}
                        chosenId={chosen?.id}
                        onChoose={choose}
                    />
                ))}
            </div>
            {chosen !== undefined && (
                <div className="card xl:w-[420px] xl:shrink-0">
                    <DemoRequestDetail
                        ref={detail}
                        request={chosen}
                        canManage={canManage}
                    />
                </div>
            )}
        </div>
    );
}
