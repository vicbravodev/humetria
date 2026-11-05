import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The way forward of a step (board V4-Quejas-Form): what is missing to leave it, what the server refused, and one wide
 * button. The last step sends; none advances on its own.
 */
export default function StepNav({
    last,
    problem,
    refused,
    processing,
    onNext,
    onSend,
}: {
    last: boolean;
    problem: string | undefined;
    /** What the server refused when it was sent, in its words. */
    refused: string[];
    processing: boolean;
    onNext: () => void;
    onSend: () => void;
}) {
    const said = problem === undefined ? refused : [problem, ...refused];

    return (
        <div className="mt-auto flex flex-col gap-3 pt-2">
            {said.length > 0 && (
                <ul
                    role="alert"
                    className="flex flex-col gap-1 text-sm text-destructive"
                >
                    {said.map((text) => (
                        <li key={text}>{text}</li>
                    ))}
                </ul>
            )}
            <Button
                type="button"
                className="w-full"
                disabled={processing}
                onClick={last ? onSend : onNext}
            >
                {processing && <Spinner />}
                {last ? 'Enviar queja' : 'Continuar'}
            </Button>
        </div>
    );
}
