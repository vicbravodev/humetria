import { favorableText, versusCompanyText } from '@/lib/engagement/format';
import type { FeedbackItem } from '@/types';

/** Every question about the manager that reached the minimum, with its favorable share and how it stands against the company. */
export default function ManagerItems({ items }: { items: FeedbackItem[] }) {
    return (
        <section
            aria-labelledby="manager-items"
            className="flex flex-col gap-4 card p-5 sm:p-6"
        >
            <h2 id="manager-items" className="text-xl font-semibold">
                Pregunta por pregunta
            </h2>
            <ul className="divide-y">
                {items.map((item) => (
                    <li
                        key={item.key}
                        className="flex flex-wrap items-start justify-between gap-x-6 gap-y-1 py-4 first:pt-0 last:pb-0"
                    >
                        <p className="max-w-[560px] text-pretty">{item.text}</p>
                        <span className="flex flex-col sm:items-end">
                            <strong className="font-semibold tabular-nums">
                                {favorableText(item.favorable)}
                            </strong>
                            <span className="text-caption text-muted-foreground">
                                {versusCompanyText(item.vsCompany)}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
