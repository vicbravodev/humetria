import { REFERRAL_STEPS } from '@/lib/referral';
import type { ReferralCounts } from '@/types';

/** How many of the people who require a clinical assessment are still to channel, channeled and attended. */
export default function ReferralSummary({
    counts,
}: {
    counts: ReferralCounts;
}) {
    const figures = [counts.pending, counts.referred, counts.attended];

    return (
        <dl
            aria-label="Resumen de la canalización"
            className="grid grid-cols-3 gap-3"
        >
            {REFERRAL_STEPS.map((label, index) => (
                <div key={label} className="flex flex-col gap-1 card px-4 py-4">
                    <dt className="text-caption text-muted-foreground">
                        {label}
                    </dt>
                    <dd className="text-2xl font-semibold tabular-nums">
                        {figures[index]}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
