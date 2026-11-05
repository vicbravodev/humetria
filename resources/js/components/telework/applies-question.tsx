import { ChevronLeft } from 'lucide-react';
import ChoiceField from '@/components/humetria/choice-field';
import { Button } from '@/components/ui/button';
import type { AppliesAnswer, QUESTIONS } from '@/lib/telework/applies';
import { cn } from '@/lib/utils';

/**
 * One question of «¿Te aplica la NOM-037?» (board V2-Aplica): where it goes, the choices as rows, and «Siguiente», which
 * waits for an answer. «No lo sé» shows how to tell instead of letting it through.
 */
export default function AppliesQuestion({
    question,
    number,
    answer,
    onAnswer,
    onBack,
    onNext,
    processing,
}: {
    question: (typeof QUESTIONS)[number];
    number: number;
    answer: AppliesAnswer | null;
    onAnswer: (answer: AppliesAnswer) => void;
    onBack: () => void;
    onNext: () => void;
    processing: boolean;
}) {
    const unsure = answer === 'unsure';

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center gap-3.5">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Pregunta anterior"
                    onClick={onBack}
                >
                    <ChevronLeft aria-hidden className="size-5" />
                </Button>
                <div aria-hidden className="flex gap-1.5">
                    {[1, 2].map((dot) => (
                        <span
                            key={dot}
                            className={cn(
                                'h-1.5 w-7 rounded-full',
                                dot <= number ? 'bg-primary' : 'bg-track',
                            )}
                        />
                    ))}
                </div>
                <span className="text-sm text-muted-foreground">
                    Pregunta {number} de 2
                </span>
            </div>
            <ChoiceField
                name={question.key}
                legend={question.title}
                hint={question.hint}
                options={question.options}
                value={answer}
                onChange={onAnswer}
                size="lg"
            />
            {unsure && (
                <div className="card px-4 py-4 md:px-5">
                    <p className="font-medium">Una forma rápida de saberlo</p>
                    <p className="text-muted-foreground">
                        En una semana de 5 días, más del 40 % son más de 2 días
                        completos desde casa. Si alguien va 3 días a la oficina
                        y 2 a casa, todavía no aplica.
                    </p>
                </div>
            )}
            <div>
                <Button
                    size="lg"
                    onClick={onNext}
                    disabled={answer === null || unsure || processing}
                >
                    Siguiente
                </Button>
            </div>
        </div>
    );
}
