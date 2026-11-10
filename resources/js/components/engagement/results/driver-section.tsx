import ItemRow from '@/components/engagement/results/item-row';
import { favorableText } from '@/lib/engagement/format';
import type { EngagementItemSection } from '@/types';

/** The questions of one theme, with the score of the theme when it has one. */
export default function DriverSection({
    section,
}: {
    section: EngagementItemSection;
}) {
    return (
        <section
            aria-labelledby={`section-${section.key}`}
            className="grid gap-6 card p-5 sm:p-6 lg:grid-cols-3 lg:gap-x-10"
        >
            <div className="flex flex-col gap-2">
                <h2
                    id={`section-${section.key}`}
                    className="text-lg font-semibold"
                >
                    {section.label}
                </h2>
                {section.score !== null && (
                    <p className="text-sm text-muted-foreground">
                        Respuestas favorables al tema:{' '}
                        <strong className="font-medium text-foreground">
                            {favorableText(section.score)}
                        </strong>
                    </p>
                )}
                {section.note !== null && (
                    <p className="text-sm text-muted-foreground">
                        {section.note}
                    </p>
                )}
            </div>
            <ol className="flex flex-col divide-y lg:col-span-2">
                {section.items.map((item) => (
                    <ItemRow key={item.key} item={item} />
                ))}
            </ol>
        </section>
    );
}
