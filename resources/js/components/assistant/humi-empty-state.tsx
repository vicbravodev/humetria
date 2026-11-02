import Humi from '@/components/assistant/humi';
import HumiSuggestions from '@/components/assistant/humi-suggestions';

/** What the panel shows before the first question: Humi greeting and questions to start from, by the page open. */
export default function HumiEmptyState({
    suggestions,
    onPick,
}: {
    suggestions: readonly string[];
    onPick: (text: string) => void;
}) {
    return (
        <div className="flex flex-col items-center gap-5 px-5 py-8 text-center">
            <Humi expression="hola" size={72} animated />
            <div className="flex flex-col gap-1">
                <h2 className="text-xl font-semibold">Hola, soy Humi</h2>
                <p className="text-base text-muted-foreground">
                    Te ayudo a ver cómo va tu cumplimiento y a decidir qué
                    sigue.
                </p>
            </div>
            <HumiSuggestions
                suggestions={suggestions}
                onPick={onPick}
                variant="list"
            />
        </div>
    );
}
