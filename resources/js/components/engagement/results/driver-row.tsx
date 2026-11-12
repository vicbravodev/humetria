import TopicBar from '@/components/engagement/results/topic-bar';
import { Badge } from '@/components/ui/badge';
import type { Topic } from '@/lib/engagement/results';

/**
 * One theme of «Dónde actuar primero»: its name and priority, and its favorable share against the company's engagement.
 * A theme of two questions, or one the survey asked only in part, is read question by question, each under its own
 * minimum.
 */
export default function DriverRow({
    topic,
    reference,
    index,
}: {
    topic: Topic;
    reference: number | null;
    /** Its place in the list, so the bars grow one after another. */
    index: number;
}) {
    const { driver, priority } = topic;
    const byQuestion = driver.descriptive || driver.partial;

    return (
        <li className="grid gap-3 py-5 first:pt-0 last:pb-0 md:grid-cols-[200px_1fr] md:gap-8">
            <div className="flex flex-col items-start gap-2">
                <span className="font-medium">{driver.label}</span>
                {priority !== null && (
                    <Badge
                        variant={
                            priority.level === 'high' ? 'warning' : 'secondary'
                        }
                    >
                        Prioridad {priority.levelLabel.toLowerCase()}
                    </Badge>
                )}
            </div>
            <div className="flex min-w-0 flex-col gap-4">
                {byQuestion ? (
                    driver.items.map((item, position) => (
                        <TopicBar
                            key={item.key}
                            text={item.text}
                            favorable={item.favorable}
                            reference={reference}
                            index={index + position}
                        />
                    ))
                ) : (
                    <TopicBar
                        text={null}
                        favorable={driver.favorable}
                        reference={reference}
                        index={index}
                    />
                )}
            </div>
        </li>
    );
}
