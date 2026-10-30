import { participationText } from '@/lib/results';
import { staffText } from '@/lib/work-centers';
import type { Participation, ResultsCampaign } from '@/types';

/** When the evaluation ran, to how many people and how many answered (design system: ReportHeader). */
export default function ResultsFacts({
    campaign,
    participation,
}: {
    campaign: ResultsCampaign;
    participation: Participation | null;
}) {
    const facts = [
        {
            label: 'Periodo',
            value: `${campaign.launchedOn ?? '—'} al ${campaign.closedOn ?? 'hoy'}`,
        },
        {
            label: 'Personal',
            value:
                campaign.workers === null ? '—' : staffText(campaign.workers),
        },
        ...(participation === null
            ? []
            : [
                  {
                      label: 'Participación',
                      value: participationText(participation),
                  },
              ]),
        ...(campaign.sampleSize === null
            ? []
            : [
                  {
                      label: 'Muestra',
                      value: `${campaign.sampleSize} personas`,
                  },
              ]),
    ];

    return (
        <dl className="grid gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2 lg:grid-cols-4">
            {facts.map((fact) => (
                <div key={fact.label} className="grid gap-1">
                    <dt className="text-[13px] leading-[18px] font-medium tracking-[0.02em] text-muted-foreground uppercase">
                        {fact.label}
                    </dt>
                    <dd className="font-medium">{fact.value}</dd>
                </div>
            ))}
        </dl>
    );
}
