import OfflineBadge from '@/components/attendance/offline-badge';
import OfflineMarkDecision from '@/components/attendance/offline-mark-decision';
import Initials from '@/components/humetria/initials';
import { waitingFor } from '@/lib/attendance/corrections';
import { markName } from '@/lib/attendance/next-step';
import {
    detailHours,
    flagText,
    signerText,
} from '@/lib/attendance/offline-marks';
import type { OfflineMarkDetail as Detail } from '@/types';

/**
 * The mark in view: who made it and what, every hour with its name (declared, counted, since when without a connection,
 * the QR's window if it had one, when it arrived), what the server warns of and who signed it, then the decision. What
 * the person wrote is only for whoever resolves, and is masked in the recordings of the panel.
 */
export default function OfflineMarkDetail({
    detail,
    canResolve,
}: {
    detail: Detail;
    canResolve: boolean;
}) {
    const signer = signerText(detail.signer);

    return (
        <section
            id="offline-mark-detail"
            aria-label="Detalle de la marca"
            className="flex min-w-0 scroll-mt-4 flex-col gap-6 card p-5 md:p-7"
        >
            <div className="flex items-center gap-3.5">
                <Initials name={detail.person.name} />
                <div className="flex min-w-0 flex-col">
                    <h2 className="text-xl leading-7 font-semibold text-balance">
                        {detail.person.name} marcó su{' '}
                        {markName(detail.type).toLowerCase()} sin conexión
                    </h2>
                    <span className="text-sm text-muted-foreground">
                        {detail.person.jobTitle} · Llegó{' '}
                        {waitingFor(detail.ageDays)}
                    </span>
                </div>
                <span className="ml-auto hidden sm:block">
                    <OfflineBadge view="offline" />
                </span>
            </div>
            <dl className="grid gap-x-6 gap-y-3 rounded-xl bg-muted/50 p-5 sm:grid-cols-2">
                {detailHours(detail).map((hour) => (
                    <div key={hour.label} className="flex flex-col">
                        <dt className="text-sm text-muted-foreground">
                            {hour.label}
                        </dt>
                        <dd className="font-medium tabular-nums">
                            {hour.value}
                        </dd>
                    </div>
                ))}
            </dl>
            {detail.flags.length > 0 && (
                <section className="flex flex-col gap-2">
                    <h3 className="text-base font-semibold">Para revisar</h3>
                    <ul className="flex list-disc flex-col gap-1 pl-5 text-muted-foreground">
                        {detail.flags.map((flag) => (
                            <li key={flag}>{flagText(flag)}</li>
                        ))}
                    </ul>
                </section>
            )}
            {signer !== null && (
                <p className="text-sm text-muted-foreground">{signer}.</p>
            )}
            {detail.justification !== null && (
                <blockquote className="ph-mask border-l-[3px] pl-4 text-pretty">
                    “{detail.justification}”
                    <footer className="text-sm text-muted-foreground">
                        Lo escribió {detail.person.name}
                    </footer>
                </blockquote>
            )}
            <OfflineMarkDecision
                key={detail.id}
                detail={detail}
                canResolve={canResolve}
            />
        </section>
    );
}
