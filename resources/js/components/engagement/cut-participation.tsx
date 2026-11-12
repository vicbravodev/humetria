import { Lock } from 'lucide-react';
import { useState } from 'react';
import BarMeter from '@/components/humetria/bar-meter';
import { pointsText } from '@/lib/engagement/format';
import { percent } from '@/lib/evaluation';
import { cn } from '@/lib/utils';
import type { ClimateCut } from '@/types';

/**
 * Participation by group (pantalla Clima · Avance): one cut at a time in tabs, each group with its share against the
 * whole survey (the vertical mark) and how many answered of how many. The groups under the minimum are not here, so
 * nobody can be picked out.
 */
export default function CutParticipation({
    cuts,
    minGroup,
    overall,
}: {
    cuts: ClimateCut[];
    minGroup: number;
    /** The share of the whole survey: the reference of every bar. */
    overall: number | null;
}) {
    const [key, setKey] = useState(cuts[0]?.key);
    const cut = cuts.find((candidate) => candidate.key === key) ?? cuts[0];

    return (
        <section
            aria-labelledby="cuts-title"
            className="flex min-w-0 flex-col gap-5 card p-6"
        >
            <h2 id="cuts-title" className="text-lg leading-7 font-semibold">
                Participación por grupo
            </h2>
            <div
                role="tablist"
                aria-label="Ver por"
                className="inline-flex max-w-full gap-0.5 self-start overflow-x-auto rounded-[10px] border bg-muted p-[3px]"
            >
                {cuts.map((candidate) => (
                    <button
                        key={candidate.key}
                        type="button"
                        role="tab"
                        aria-selected={candidate.key === cut?.key}
                        onClick={() => setKey(candidate.key)}
                        className={cn(
                            'inline-flex h-11 shrink-0 items-center rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:h-[38px]',
                            candidate.key === cut?.key &&
                                'bg-card text-foreground shadow-raised',
                        )}
                    >
                        {candidate.title}
                    </button>
                ))}
            </div>
            {cut === undefined || cut.groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Ningún grupo de este tipo llega a {minGroup} personas.
                </p>
            ) : (
                <ul className="flex flex-col gap-4">
                    {cut.groups.map((group, index) => {
                        const share = percent(group.answered, group.invited);

                        return (
                            <li
                                key={group.label}
                                className="flex flex-col gap-1.5"
                            >
                                <div className="flex flex-wrap justify-between gap-x-3 text-sm">
                                    <span className="font-medium">
                                        {group.label}
                                    </span>
                                    <span className="tabular-nums">
                                        <strong className="font-semibold">
                                            {share} %
                                        </strong>{' '}
                                        <span className="text-muted-foreground">
                                            {group.answered} de {group.invited}
                                            {overall !== null &&
                                                ` · ${pointsText(share - overall)}`}
                                        </span>
                                    </span>
                                </div>
                                <BarMeter
                                    percent={share}
                                    tooltip={`Contestaron en ${group.label}: ${share} %`}
                                    index={index}
                                    {...(overall === null
                                        ? {}
                                        : { reference: overall })}
                                />
                            </li>
                        );
                    })}
                </ul>
            )}
            <div className="flex flex-col gap-1 text-sm text-muted-foreground">
                {overall !== null && (
                    <p>La marca vertical es el total: {overall} %.</p>
                )}
                <p className="flex items-center gap-1.5">
                    <Lock aria-hidden className="size-4" />
                    Los grupos de menos de {minGroup} personas no se muestran.
                </p>
            </div>
        </section>
    );
}
