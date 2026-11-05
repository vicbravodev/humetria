import { cn } from '@/lib/utils';
import type { FlowTopic, TopicState } from '@/lib/question-flow';

const TONES: Record<TopicState, string> = {
    done: 'border-transparent bg-accent text-primary',
    base: 'border-dashed border-input bg-muted text-foreground',
    open: 'border-border bg-card text-muted-foreground',
};

const STATES: Record<TopicState, string> = {
    done: 'cubierto',
    base: 'con texto base',
    open: 'por contestar',
};

/** The topics the norm asks for, as chips (board V2-Politica): covered, with a base text (dotted) or still open. */
export default function TopicChips({
    topics,
    label,
    note,
}: {
    topics: (FlowTopic & { state: TopicState })[];
    label: string;
    note: string;
}) {
    return (
        <div className="flex flex-col gap-2">
            <ul aria-label={label} className="flex flex-wrap gap-2">
                {topics.map((topic) => (
                    <li
                        key={topic.key}
                        title={topic.label}
                        className={cn(
                            'inline-flex h-[30px] items-center rounded-full border px-2.5 text-caption font-semibold',
                            TONES[topic.state],
                        )}
                    >
                        {topic.key})
                        <span className="sr-only">{` ${topic.label}: ${STATES[topic.state]}`}</span>
                    </li>
                ))}
            </ul>
            <p className="text-caption text-muted-foreground">{note}</p>
        </div>
    );
}
