import UrgencyList from '@/components/humetria/urgency-list';
import type { AttentionItem } from '@/lib/attention';

/** «Atiende primero» of the console: what needs the superadmin today, or a calm line when nothing does. */
export default function AttentionSection({
    items,
}: {
    items: AttentionItem[];
}) {
    return (
        <section aria-labelledby="attention" className="flex flex-col gap-3">
            <h2 id="attention" className="text-xl leading-7 font-semibold">
                Atiende primero
            </h2>
            {items.length === 0 ? (
                <p className="card p-5 text-muted-foreground">
                    Todo en orden: nada espera tu atención hoy.
                </p>
            ) : (
                <UrgencyList items={items} />
            )}
        </section>
    );
}
