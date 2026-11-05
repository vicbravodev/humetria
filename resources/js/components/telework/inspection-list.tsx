import InspectionItem from '@/components/telework/inspection-item';
import type { InspectionItem as Item } from '@/types';

/** «Te falta» or «Lo puedes mostrar» (board V3-Inspeccion): a card with how many of the rows, and each row. */
export default function InspectionList({
    id,
    title,
    items,
    applicable,
    empty,
}: {
    id: string;
    title: string;
    items: Item[];
    applicable: number;
    empty: string;
}) {
    return (
        <section
            aria-labelledby={id}
            className="@container overflow-hidden card"
        >
            <div className="flex items-center justify-between gap-4 bg-muted px-5 py-3.5">
                <h2 id={id} className="font-semibold">
                    {title}
                </h2>
                <span className="text-sm text-muted-foreground">
                    {items.length} de {applicable} renglones
                </span>
            </div>
            {items.length === 0 ? (
                <p className="border-t px-5 py-4 text-muted-foreground">
                    {empty}
                </p>
            ) : (
                <ul>
                    {items.map((item, index) => (
                        <InspectionItem
                            key={item.numeral}
                            item={item}
                            primary={index === 0}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
