import { ChevronDown } from 'lucide-react';
import ItemRank from '@/components/results/item-rank';
import ItemRow from '@/components/results/item-row';
import ItemsTable from '@/components/results/items-table';
import { rankItems } from '@/lib/results';
import type { ItemSection } from '@/types';

/**
 * Every question of the guide: the worst answered first, then each section folded until opened, its questions from
 * the most unfavorable, with the count of each option behind them. Always the whole center.
 */
export default function ItemsResults({
    sections,
}: {
    sections: ItemSection[];
}) {
    return (
        <section className="grid gap-4">
            <div className="grid gap-1">
                <h2 className="text-lg font-semibold">Por pregunta</h2>
                <p className="max-w-[75ch] text-sm text-muted-foreground">
                    Cómo contestó todo el centro cada pregunta. Una respuesta es
                    desfavorable cuando da 3 o 4 puntos en la escala de esa
                    pregunta (Tablas 2 y 5 de la norma): «Siempre» o «Casi
                    siempre» en la mayoría, «Nunca» o «Casi nunca» en las que
                    preguntan por algo favorable. Por área no se muestra: la
                    resta con el centro revelaría a grupos pequeños.
                </p>
            </div>
            <ItemRank sections={sections} />
            {sections.map((section, index) => (
                <details
                    key={index}
                    className="group rounded-xl border bg-card p-4 open:pb-5 sm:px-5"
                >
                    <summary className="flex cursor-pointer list-none items-baseline justify-between gap-4 rounded-md font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        <span>{section.title ?? section.instruction}</span>
                        <span className="shrink-0 text-sm font-normal whitespace-nowrap text-muted-foreground">
                            {section.items.length} preguntas
                            <ChevronDown
                                aria-hidden="true"
                                className="ml-2 inline-block size-4 transition-transform duration-150 ease-out group-open:rotate-180 motion-reduce:transition-none"
                            />
                        </span>
                    </summary>
                    <ol className="mt-4 divide-y border-t pt-3">
                        {rankItems(section.items).map((item) => (
                            <ItemRow key={item.number} item={item} />
                        ))}
                    </ol>
                    <details className="group/table mt-4">
                        <summary className="w-fit cursor-pointer list-none text-sm font-medium text-primary underline-offset-4 hover:underline [&::-webkit-details-marker]:hidden">
                            <span className="group-open/table:hidden">
                                Ver los conteos por opción
                            </span>
                            <span className="hidden group-open/table:inline">
                                Ocultar los conteos por opción
                            </span>
                        </summary>
                        <div className="mt-3">
                            <ItemsTable section={section} />
                        </div>
                    </details>
                </details>
            ))}
        </section>
    );
}
