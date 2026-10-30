import { instrumentsText, quotasText } from '@/lib/campaigns';
import { staffText } from '@/lib/work-centers';
import type { CampaignDetail } from '@/types';

/** What was decided for the campaign: its people, guides and who answers Guía III. */
export default function CampaignFacts({
    campaign,
}: {
    campaign: CampaignDetail;
}) {
    const facts = [
        {
            label: 'Personal',
            value:
                campaign.workers === null ? '—' : staffText(campaign.workers),
        },
        { label: 'Guías', value: instrumentsText(campaign.instruments) },
        ...(campaign.populationLabel === null ||
        !campaign.instruments.includes('Guía III')
            ? []
            : [
                  {
                      label: 'Guía III',
                      value:
                          campaign.population === 'sample'
                              ? `Muestra de ${campaign.sampleSize ?? 0}: ${quotasText(campaign.quotas)}`
                              : 'Todas las personas',
                  },
              ]),
        ...(campaign.closesOn === null
            ? []
            : [{ label: 'Cierra', value: campaign.closesOn }]),
    ];

    return (
        <dl className="grid gap-4 rounded-xl border bg-card p-6 sm:grid-cols-2">
            {facts.map((fact) => (
                <div key={fact.label} className="grid gap-1">
                    <dt className="text-sm text-muted-foreground">
                        {fact.label}
                    </dt>
                    <dd className="font-medium">{fact.value}</dd>
                </div>
            ))}
        </dl>
    );
}
