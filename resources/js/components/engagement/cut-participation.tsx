import { useState } from 'react';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import { percent } from '@/lib/evaluation';
import type { ClimateCut } from '@/types';

/**
 * Participation by group (pantalla Eval-Activa): one cut at a time, each group with how many answered of how many were
 * invited. The groups under the minimum are not here, so nobody can be picked out.
 */
export default function CutParticipation({
    cuts,
    minGroup,
}: {
    cuts: ClimateCut[];
    minGroup: number;
}) {
    const [key, setKey] = useState(cuts[0]?.key);
    const cut = cuts.find((candidate) => candidate.key === key) ?? cuts[0];

    return (
        <section
            aria-labelledby="cuts-title"
            className="flex flex-col gap-5 card p-6"
        >
            <div className="flex flex-col gap-2">
                <h2 id="cuts-title" className="text-lg leading-7 font-semibold">
                    Participación por grupo
                </h2>
                <p className="text-sm text-muted-foreground">
                    Aquí ves cuántas personas contestaron, nunca qué ni quién.
                    Los grupos de menos de {minGroup} personas no se muestran.
                </p>
            </div>
            <div
                className="flex flex-wrap gap-2"
                role="group"
                aria-label="Ver por"
            >
                {cuts.map((candidate) => (
                    <Button
                        key={candidate.key}
                        type="button"
                        size="sm"
                        variant={
                            candidate.key === cut?.key ? 'default' : 'outline'
                        }
                        aria-pressed={candidate.key === cut?.key}
                        onClick={() => setKey(candidate.key)}
                    >
                        {candidate.title}
                    </Button>
                ))}
            </div>
            {cut === undefined || cut.groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Ningún grupo de este tipo llega a {minGroup} personas.
                </p>
            ) : (
                <ul className="flex flex-col gap-4">
                    {cut.groups.map((group) => (
                        <li key={group.label} className="flex flex-col gap-1.5">
                            <div className="flex justify-between gap-3 text-sm">
                                <span className="font-medium">
                                    {group.label}
                                </span>
                                <span className="tabular-nums">
                                    <strong className="font-semibold">
                                        {group.answered}
                                    </strong>{' '}
                                    <span className="text-muted-foreground">
                                        de {group.invited} ·{' '}
                                        {percent(group.answered, group.invited)}
                                        &nbsp;%
                                    </span>
                                </span>
                            </div>
                            <ProgressBar
                                value={group.answered}
                                max={group.invited}
                                label={`Contestaron en ${group.label}`}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
