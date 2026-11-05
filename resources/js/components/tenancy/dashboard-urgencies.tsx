import UrgencyList from '@/components/humetria/urgency-list';
import type { Urgency } from '@/types';

/**
 * "Atiende primero": what cannot wait, before where each route goes; nothing when nothing is urgent. A route calls it
 * "Antes de seguir" (board V3-Movil).
 */
export default function DashboardUrgencies({
    items,
    title = 'Atiende primero',
}: {
    items: Urgency[];
    title?: string;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="urgent" className="flex flex-col gap-3">
            <h2 id="urgent" className="text-xl leading-7 font-semibold">
                {title}
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
