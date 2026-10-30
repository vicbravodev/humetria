import ItemRow from '@/components/results/item-row';
import { rankItems } from '@/lib/results';
import type { ItemSection } from '@/types';

const SHOWN = 6;

/** The questions the center answered worst, across every section: where an intervention can start. */
export default function ItemRank({ sections }: { sections: ItemSection[] }) {
    const worst = rankItems(sections.flatMap((section) => section.items))
        .filter((item) => item.unfavorable !== null && item.unfavorable > 0)
        .slice(0, SHOWN);

    if (worst.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3">
            <h3 className="font-semibold">
                Las {worst.length} preguntas con más respuestas desfavorables
            </h3>
            <ol className="divide-y rounded-xl border bg-card p-4 sm:p-5">
                {worst.map((item) => (
                    <ItemRow key={item.number} item={item} />
                ))}
            </ol>
        </div>
    );
}
