import { Badge } from '@/components/ui/badge';
import ReviewPhotos from '@/components/telework/review-photos';
import { describeDate } from '@/lib/calendar-date';
import { evidenceKindLabel } from '@/lib/telework/review';
import type { ReviewEvidence as Evidence } from '@/types';

/** What the Comisión asked for about this place, whether it arrived, and the photos that did. */
export default function ReviewEvidence({
    tenantSlug,
    evidence,
}: {
    tenantSlug: string;
    evidence: Evidence[];
}) {
    if (evidence.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-xl border bg-card">
            <h2 className="border-b bg-muted px-5 py-3 text-lg font-semibold">
                Evidencia pedida
            </h2>
            <ul className="divide-y">
                {evidence.map((request) => (
                    <li
                        key={request.id}
                        className="flex flex-col gap-2 px-5 py-4"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="font-medium">
                                {evidenceKindLabel(request.kind)} ·{' '}
                                {describeDate(request.requestedOn)}
                            </p>
                            <Badge
                                variant={
                                    request.fulfilled ? 'success' : 'warning'
                                }
                            >
                                {request.fulfilled ? 'Atendida' : 'Pendiente'}
                            </Badge>
                        </div>
                        <p className="text-[15px] leading-[22px]">
                            {request.note}
                        </p>
                        <ReviewPhotos
                            tenantSlug={tenantSlug}
                            photos={request.photos}
                            evidence
                        />
                    </li>
                ))}
            </ul>
        </section>
    );
}
