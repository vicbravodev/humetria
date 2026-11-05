import { Check } from 'lucide-react';
import AccidentStep from '@/components/telework/accident-step';
import type { AccidentNotice, Choice } from '@/types';

/** Steps 1 and 2 of an accident once saved (board V3-Aviso): what was recorded, read only, and that it reached the portal. */
export default function AccidentAnswered({
    notice,
    choices,
}: {
    notice: AccidentNotice;
    choices: Choice[];
}) {
    return (
        <>
            <AccidentStep
                number={1}
                state="done"
                title="Registra qué se hizo"
                text="Lo que registraste:"
            >
                <ul className="flex flex-wrap gap-2">
                    {choices
                        .filter((choice) =>
                            notice.actions.includes(choice.value),
                        )
                        .map((choice) => (
                            <li
                                key={choice.value}
                                className="rounded-lg bg-accent px-3 py-1.5 text-sm font-medium"
                            >
                                {choice.label}
                            </li>
                        ))}
                </ul>
                {notice.note !== null && (
                    <p className="text-sm whitespace-pre-line">{notice.note}</p>
                )}
            </AccidentStep>
            <AccidentStep
                number={2}
                state="done"
                title={`Avísale a ${notice.person} que lo leíste`}
                text="Le llega a su portal lo que registraste. Así sabe que su aviso no se perdió."
            >
                <p className="flex items-center gap-2 text-sm font-medium text-primary">
                    <Check aria-hidden className="size-4" />
                    Enviado a su portal
                </p>
            </AccidentStep>
        </>
    );
}
