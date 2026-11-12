import ItemRow from '@/components/engagement/results/item-row';
import { lowestFirst, sectionScore } from '@/lib/engagement/results';
import type { EngagementItemSection } from '@/types';

/** The questions of one theme from the least favorable, each against the figure of the theme (its vertical mark). */
export default function DriverSection({
    section,
}: {
    section: EngagementItemSection;
}) {
    const score = sectionScore(section);

    return (
        <section
            aria-labelledby={`section-${section.key}`}
            className="flex flex-col gap-5 card p-5 sm:p-6"
        >
            <div className="flex flex-col gap-1">
                <h2
                    id={`section-${section.key}`}
                    className="text-lg font-semibold"
                >
                    {section.label}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {score === null
                        ? 'De menor a mayor.'
                        : `De menor a mayor. La marca vertical es el tema: ${score} %.`}
                    {section.note !== null && ` ${section.note}`}
                </p>
            </div>
            <ol className="flex flex-col divide-y">
                {lowestFirst(section.items).map((item) => (
                    <ItemRow key={item.key} item={item} reference={score} />
                ))}
            </ol>
        </section>
    );
}
