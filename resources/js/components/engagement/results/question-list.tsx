import { favorableText } from '@/lib/engagement/format';
import type { EngagementItem } from '@/types';

/** Questions with the share of favorable answers each got, under a heading; or why there are none to list. */
export default function QuestionList({
    heading,
    items,
}: {
    heading: string;
    items: EngagementItem[];
}) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="font-semibold">{heading}</h3>
            {items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Aún no hay preguntas con respuestas suficientes.
                </p>
            ) : (
                <ol className="flex flex-col gap-3">
                    {items.map((item) => (
                        <li
                            key={item.key}
                            className="flex items-start justify-between gap-4 text-sm"
                        >
                            <span className="text-pretty">{item.text}</span>
                            <strong className="font-semibold tabular-nums">
                                {favorableText(item.favorable)}
                            </strong>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}
