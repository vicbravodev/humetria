import ItemsTable from '@/components/results/items-table';
import type { ItemSection } from '@/types';

/** Every question of the guide, by section, each one folded until opened. Always the whole center. */
export default function ItemsResults({
    sections,
}: {
    sections: ItemSection[];
}) {
    return (
        <section className="grid gap-3">
            <h2 className="text-xl font-semibold">Por pregunta</h2>
            <p className="text-sm text-muted-foreground">
                Cómo contestó todo el centro cada pregunta. Por área no se
                muestra: la resta con el centro revelaría a grupos pequeños.
            </p>
            {sections.map((section, index) => (
                <details
                    key={index}
                    className="rounded-xl border bg-card p-4 [&_summary]:cursor-pointer"
                >
                    <summary className="font-medium">
                        {section.title ?? section.instruction}
                        <span className="text-muted-foreground">
                            {' '}
                            · {section.items.length} preguntas
                        </span>
                    </summary>
                    <div className="mt-4">
                        <ItemsTable section={section} />
                    </div>
                </details>
            ))}
        </section>
    );
}
