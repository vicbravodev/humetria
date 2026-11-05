import UrgencyList from '@/components/humetria/urgency-list';
import type { Urgency } from '@/types';

/** "Atiende primero": what cannot wait, before where each route goes; nothing when nothing is urgent. */
export default function DashboardUrgencies({ items }: { items: Urgency[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="urgent" className="flex flex-col gap-3">
            <h2 id="urgent" className="text-xl leading-7 font-semibold">
                Atiende primero
            </h2>
            <UrgencyList
                items={items.map((item) => ({
                    key: item.href,
                    tag: item.norm,
                    title: item.title,
                    detail: item.detail,
                    action: item.action,
                    href: item.href,
                }))}
            />
        </section>
    );
}
