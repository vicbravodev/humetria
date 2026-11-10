import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import CutParticipation from '@/components/engagement/cut-participation';
import ParticipationCard from '@/components/engagement/participation-card';
import { Button } from '@/components/ui/button';
import type { ClimateParticipation } from '@/types';

/**
 * The survey once it closed (pantalla Eval-Cerrada): how many answered, how each group did, and the way to the results,
 * which exist only now that nobody can be told apart by watching the count grow.
 */
export default function ClosedFollowUp({
    participation,
    resultsUrl,
}: {
    participation: ClimateParticipation;
    resultsUrl: string | null;
}) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
                <ParticipationCard participation={participation} closed />
                <CutParticipation
                    cuts={participation.cuts}
                    minGroup={participation.minGroup}
                />
            </div>
            {resultsUrl !== null && (
                <section
                    aria-labelledby="results-title"
                    className="flex flex-wrap items-center justify-between gap-4 card p-6"
                >
                    <div className="flex max-w-[620px] flex-col gap-1">
                        <h2
                            id="results-title"
                            className="text-lg leading-7 font-semibold"
                        >
                            Los resultados están listos
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Se muestran por grupos, nunca por persona, y solo
                            los grupos con suficientes respuestas.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={resultsUrl}>
                            Ver resultados
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </section>
            )}
        </div>
    );
}
