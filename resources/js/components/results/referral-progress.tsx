import ProgressBar from '@/components/humetria/progress-bar';
import { channeledLine } from '@/lib/referral';
import type { ReferralCounts } from '@/types';

/** «3 de 12 canalizadas · ≈ 2 min cada una» (board V4-N035-Canalizacion), with its bar. */
export default function ReferralProgress({
    counts,
}: {
    counts: ReferralCounts;
}) {
    const line = channeledLine(counts);

    return (
        <div className="flex w-full max-w-[280px] flex-col gap-2 self-end">
            <p className="text-sm">
                <span className="font-semibold">{line.text}</span>
                <span className="text-muted-foreground">
                    {' '}
                    · ≈ 2 min cada una
                </span>
            </p>
            <ProgressBar value={line.done} max={line.total} label={line.text} />
        </div>
    );
}
