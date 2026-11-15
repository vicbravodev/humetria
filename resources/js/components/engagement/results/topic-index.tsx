import { Lock } from 'lucide-react';
import { sectionScore } from '@/lib/engagement/results';
import { cn } from '@/lib/utils';
import type { EngagementItemSection } from '@/types';

/** The themes of «Por pregunta» with their figure: choosing one shows its questions beside the list. */
export default function TopicIndex({
    sections,
    selected,
    onSelect,
}: {
    sections: EngagementItemSection[];
    selected: string | null;
    onSelect: (key: string) => void;
}) {
    return (
        <nav aria-label="Temas" className="card p-2">
            <ul className="flex flex-col gap-0.5">
                {sections.map((section) => {
                    const score = sectionScore(section);

                    return (
                        <li key={section.key}>
                            <button
                                type="button"
                                aria-current={
                                    selected === section.key
                                        ? 'true'
                                        : undefined
                                }
                                onClick={() => onSelect(section.key)}
                                className={cn(
                                    'flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-3 text-left transition-colors duration-150 hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    selected === section.key &&
                                        'bg-accent font-medium hover:bg-accent',
                                )}
                            >
                                {section.label}
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {score === null ? (
                                        <Lock
                                            aria-label="Protegido"
                                            className="size-4"
                                        />
                                    ) : (
                                        `${score} %`
                                    )}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
