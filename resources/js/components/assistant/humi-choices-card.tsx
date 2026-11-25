import { Button } from '@/components/ui/button';
import type { ChoiceState } from '@/lib/assistant/chosen-choice';
import type { ToolCard } from '@/lib/assistant/tool-cards';

/**
 * The question Humi asks when it is not sure what the person meant, with an option per button: a press sends the text of
 * the option as the person's own message. Once the person has answered it stays as a record, with the pressed one marked.
 */
export default function HumiChoicesCard({
    card,
    choice,
}: {
    card: Extract<ToolCard, { render: 'choices' }>;
    choice: ChoiceState;
}) {
    return (
        <div
            role="group"
            aria-label={card.title}
            className="humi-enter flex flex-col gap-3 rounded-lg border bg-card p-4"
        >
            <p className="text-base font-medium">{card.title}</p>
            <div className="flex flex-wrap gap-2">
                {card.rows.map((row) => {
                    const pressed = choice.chosen === row.text;

                    return (
                        <Button
                            key={row.text}
                            type="button"
                            variant={pressed ? 'default' : 'outline'}
                            disabled={choice.disabled}
                            aria-pressed={pressed}
                            onClick={() => choice.onPick(row.text)}
                            className={
                                pressed
                                    ? 'h-auto min-h-11 py-2 text-left whitespace-normal disabled:opacity-100'
                                    : 'h-auto min-h-11 py-2 text-left whitespace-normal'
                            }
                        >
                            {row.label}
                        </Button>
                    );
                })}
            </div>
        </div>
    );
}
