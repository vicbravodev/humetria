import { Lock } from 'lucide-react';
import Humi from '@/components/assistant/humi';
import HumiStarters from '@/components/assistant/humi-starters';
import type { Starter } from '@/lib/assistant/suggestions';

/**
 * What the panel shows before the first question: Humi greeting and saying what it does, the questions to start from (by
 * the page open) and what it never sees.
 */
export default function HumiEmptyState({
    starters,
    onPick,
}: {
    starters: readonly Starter[];
    onPick: (text: string) => void;
}) {
    return (
        <div className="flex flex-1 flex-col gap-6 pt-3">
            <div className="flex items-center gap-4">
                <Humi
                    expression="hola"
                    size={64}
                    animated
                    className="shrink-0"
                />
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-xl font-semibold">Hola, soy Humi</h2>
                    <p className="text-sm text-muted-foreground">
                        Leo el cumplimiento de tu empresa y te explico la norma.
                        No cambio datos.
                    </p>
                </div>
            </div>
            <div className="flex flex-col gap-2">
                <p className="text-sm font-semibold">Para empezar</p>
                <HumiStarters starters={starters} onPick={onPick} />
            </div>
            <p className="mt-auto flex items-start gap-2.5 rounded-lg bg-accent px-3.5 py-3 text-sm">
                <Lock
                    aria-hidden
                    className="mt-0.5 size-4 shrink-0 text-primary"
                />
                Humi nunca ve respuestas individuales ni la Guía I. Solo
                resultados de grupos de 5 personas o más.
            </p>
        </div>
    );
}
