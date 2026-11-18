import { useState } from 'react';
import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPagePick } from '@/types';

/** The page's own piece: one question answered by choice (which guides, how many days, how many hours), on the inverse band. */
export default function ToolPagePickSection({ pick }: { pick: ToolPagePick }) {
    const [selected, setSelected] = useState(0);
    const answer = pick.options[selected] ?? pick.options[0];

    return (
        <section
            aria-labelledby="pick-title"
            className={cn(WRAP, 'pt-14 lg:pt-20')}
        >
            <div className="grid gap-8 rounded-[28px] bg-foreground p-7 text-background sm:p-12 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:items-center lg:gap-12">
                <div className="grid gap-3">
                    <h2
                        id="pick-title"
                        className="text-3xl leading-tight font-semibold tracking-[-0.025em] text-balance"
                    >
                        {pick.title}
                    </h2>
                    <p className="leading-relaxed text-pretty text-background/80">
                        {pick.lead}
                    </p>
                </div>
                <div className="grid gap-5">
                    <fieldset className="grid gap-3">
                        <legend className="pb-3 text-meta text-background/80">
                            {pick.question}
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {pick.options.map((option, index) => (
                                <button
                                    key={option.label}
                                    type="button"
                                    aria-pressed={index === selected}
                                    onClick={() => setSelected(index)}
                                    data-test={`pick-${index}`}
                                    className={cn(
                                        'min-h-11 rounded-full border px-4 text-meta font-semibold transition-colors duration-150 ease-out',
                                        index === selected
                                            ? 'border-background bg-background text-foreground'
                                            : 'border-background/40 hover:border-background',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </fieldset>
                    {answer === undefined ? null : (
                        <p
                            aria-live="polite"
                            className="grid gap-1 border-t border-background/20 pt-5"
                            data-test="pick-answer"
                        >
                            <b className="text-3xl tracking-[-0.02em]">
                                {answer.answer}
                            </b>
                            <span className="leading-relaxed text-background/80">
                                {answer.detail}
                            </span>
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}
