import { useState } from 'react';
import DriverSection from '@/components/engagement/results/driver-section';
import ShareLegend from '@/components/engagement/results/share-legend';
import TopicIndex from '@/components/engagement/results/topic-index';
import { Button } from '@/components/ui/button';
import type { EngagementItemSection } from '@/types';

/**
 * «Por pregunta» (pantalla Clima · Por pregunta): the themes at the left and the questions of the chosen one at the
 * right, from the least favorable; «Todas las preguntas» shows every theme one after another.
 */
export default function ItemsBody({
    sections,
}: {
    sections: EngagementItemSection[];
}) {
    const [selected, setSelected] = useState(sections[0]?.key ?? null);
    const [all, setAll] = useState(false);
    const shown = all
        ? sections
        : sections.filter((section) => section.key === selected);

    return (
        <div className="grid items-start gap-6 lg:grid-cols-[280px_1fr]">
            <TopicIndex
                sections={sections}
                selected={all ? null : selected}
                onSelect={(key) => {
                    setSelected(key);
                    setAll(false);
                }}
            />
            <div className="flex min-w-0 flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <ShareLegend />
                    <Button
                        variant="outline"
                        aria-pressed={all}
                        onClick={() => setAll(!all)}
                    >
                        {all ? 'Un tema a la vez' : 'Todas las preguntas'}
                    </Button>
                </div>
                {shown.map((section) => (
                    <DriverSection key={section.key} section={section} />
                ))}
            </div>
        </div>
    );
}
