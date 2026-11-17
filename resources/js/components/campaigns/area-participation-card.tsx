import { Send } from 'lucide-react';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import { laggingArea, percent } from '@/lib/evaluation';
import type { AreaParticipation } from '@/types';

/**
 * How many answered in each area, never what (pantalla Eval-Activa), and a reminder only to the area that lags most:
 * the same reminder as the list's, narrowed to that area. Top to bottom (Card.md): the heading, the bars as wide as the
 * card, the reminder at the foot.
 */
export default function AreaParticipationCard({
    route,
    guide,
    areas,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    /** The guide that measures the evaluation: who answered is counted by it. */
    guide: string | null;
    areas: AreaParticipation[];
}) {
    const lagging = laggingArea(areas);

    return (
        <section
            aria-labelledby="areas-title"
            className="flex flex-col gap-6 card p-6"
        >
            <div className="flex flex-col gap-2">
                <h2
                    id="areas-title"
                    className="text-lg leading-7 font-semibold"
                >
                    Participación por área
                </h2>
                <p className="text-sm text-muted-foreground">
                    Aquí ves cuántas personas contestaron, nunca qué
                    contestaron.
                    {lagging !== null &&
                        ` ${lagging.name} es el área que más falta.`}
                </p>
            </div>
            <ul className="flex flex-col gap-4">
                {areas.map((area) => (
                    <li key={area.id} className="flex flex-col gap-1.5">
                        <div className="flex justify-between gap-3 text-sm">
                            <span className="font-medium">{area.name}</span>
                            <span className="tabular-nums">
                                <strong className="font-semibold">
                                    {area.answered}
                                </strong>{' '}
                                <span className="text-muted-foreground">
                                    de {area.total} ·{' '}
                                    {percent(area.answered, area.total)}&nbsp;%
                                </span>
                            </span>
                        </div>
                        <ProgressBar
                            value={area.answered}
                            max={area.total}
                            label={`Contestaron en ${area.name}`}
                        />
                    </li>
                ))}
            </ul>
            {lagging !== null && (
                <ResendFilteredDialog
                    campaignId={route[1]}
                    filters={{
                        status: 'pending',
                        guide,
                        area: { value: lagging.id, label: lagging.name },
                        q: '',
                    }}
                    count={lagging.pending}
                    trigger={
                        <Button variant="outline" size="sm" className="w-fit">
                            <Send aria-hidden="true" />
                            Recordar solo a {lagging.name} · {lagging.pending}
                        </Button>
                    }
                />
            )}
        </section>
    );
}
