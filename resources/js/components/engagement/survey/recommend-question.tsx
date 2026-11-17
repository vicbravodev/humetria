import QuestionHeading from '@/components/humetria/question-heading';
import { focusOnMount } from '@/lib/focus';
import { cn } from '@/lib/utils';

const SCALE = Array.from({ length: 11 }, (_, number) => number);

/**
 * The recommendation question, 0 to 10 (option A of the review): two equal rows on a phone (0 to 5, 6 to 10) and one on a
 * computer, cells of 52 px, «0 Nada probable» over the start and «10 Muy probable» under the 10, and a line that echoes the
 * number chosen, so nobody reads the 5 as the top. Native radios: the arrows move between them. Choosing only marks it.
 */
export default function RecommendQuestion({
    name,
    topic,
    text,
    value,
    onChange,
}: {
    name: string;
    topic: string;
    text: string;
    value: number | undefined;
    onChange: (value: number) => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <div
                ref={focusOnMount}
                tabIndex={-1}
                className="focus:outline-none"
            >
                <QuestionHeading topic={topic} title={text} hint={null} />
            </div>
            <fieldset className="flex flex-col gap-3 text-meta text-muted-foreground">
                <legend className="sr-only">Elija un número del 0 al 10</legend>
                <p>
                    <strong className="font-semibold text-foreground">0</strong>{' '}
                    Nada probable
                </p>
                <div className="grid grid-cols-6 gap-2 md:grid-cols-11">
                    {SCALE.map((number) => (
                        <label
                            key={number}
                            className={cn(
                                'flex min-h-[52px] cursor-pointer items-center justify-center rounded-[10px] border border-input bg-card text-[19px] font-medium text-foreground tabular-nums transition-colors duration-150 ease-out hover:bg-muted active:scale-[0.97]',
                                'has-checked:border-2 has-checked:border-primary has-checked:bg-primary has-checked:font-semibold has-checked:text-primary-foreground',
                                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={number}
                                checked={value === number}
                                onChange={() => onChange(number)}
                                className="sr-only"
                            />
                            {number}
                        </label>
                    ))}
                </div>
                <p className="grid grid-cols-6 md:grid-cols-11">
                    <span className="col-span-5 text-right md:col-span-11">
                        <strong className="font-semibold text-foreground">
                            10
                        </strong>{' '}
                        Muy probable
                    </span>
                </p>
            </fieldset>
            <p aria-live="polite" className="min-h-7 text-muted-foreground">
                {value !== undefined && (
                    <>
                        Su respuesta:{' '}
                        <strong className="font-semibold text-foreground">
                            {value}
                        </strong>{' '}
                        de 10
                    </>
                )}
            </p>
        </div>
    );
}
